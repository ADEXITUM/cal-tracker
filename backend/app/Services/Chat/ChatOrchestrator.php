<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use App\Services\Anthropic\Client as AnthropicClient;
use App\Services\Chat\Tools\ProposeMealTool;
use Throwable;

class ChatOrchestrator
{
    /** Window of past messages sent to the model. Keeps cost flat over time. */
    private const HISTORY_WINDOW = 20;

    /** Hard upper bound on tool-loop iterations per turn. */
    private const MAX_ITERATIONS = 5;

    public function __construct(
        private readonly AnthropicClient $client,
        private readonly ContextBuilder $context,
        private readonly ProposeMealTool $proposeTool,
    ) {}

    /**
     * Run one chat turn on behalf of an admin: persist their text, call the
     * model (looping until the model stops asking for tools), persist the
     * assistant reply, return both messages. Chat is shared between admins
     * so history is global; only the sender_user_id identifies who typed.
     *
     * @return list<ChatMessage>
     */
    public function turn(User $sender, string $userText): array
    {
        $userMsg = ChatMessage::create([
            'sender_user_id' => $sender->id,
            'role'           => ChatMessage::ROLE_USER,
            'content'        => [['type' => 'text', 'text' => $userText]],
        ]);

        $history = $this->buildAnthropicHistory();
        $system  = $this->context->build($sender);
        $tools   = [self::withCacheControl(ProposeMealTool::definition())];

        $combined = [];

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $response = $this->client->messages($system, $tools, $history);

            $assistantBlocks = $response['content'];
            $history[] = ['role' => 'assistant', 'content' => $assistantBlocks];

            $toolResultsForApi = [];
            foreach ($assistantBlocks as $block) {
                $type = $block['type'] ?? '';
                if ($type === 'text') {
                    $combined[] = ['type' => 'text', 'text' => (string) ($block['text'] ?? '')];
                } elseif ($type === 'tool_use') {
                    $combined[] = $this->executeTool($block, $toolResultsForApi);
                }
            }

            if (($response['stop_reason'] ?? '') !== 'tool_use') {
                break;
            }

            $history[] = ['role' => 'user', 'content' => $toolResultsForApi];
        }

        $assistantMsg = ChatMessage::create([
            // Assistant has no sender — the AI is not a member of the household.
            'sender_user_id' => null,
            'role'           => ChatMessage::ROLE_ASSISTANT,
            'content'        => $combined,
        ]);

        return [$userMsg, $assistantMsg];
    }

    /**
     * Execute a single tool_use block and shape both our stored merged-block
     * (with `result` / `status` for the frontend) and the Anthropic-shape
     * tool_result that the next API call will consume.
     *
     * @param  array<string, mixed>  $block             the assistant's tool_use block
     * @param  list<array<string, mixed>>  &$toolResults  Anthropic tool_result blocks accumulator
     * @return array<string, mixed>                     merged block to persist in our DB
     */
    private function executeTool(array $block, array &$toolResults): array
    {
        $id    = (string) ($block['id'] ?? '');
        $name  = (string) ($block['name'] ?? '');
        $input = (array)  ($block['input'] ?? []);

        if ($name !== ProposeMealTool::NAME) {
            $err = "Unknown tool: {$name}";
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $id,
                'content'     => $err,
                'is_error'    => true,
            ];
            return [
                'type'   => 'tool_use',
                'id'     => $id,
                'name'   => $name,
                'input'  => $input,
                'error'  => $err,
                'status' => 'error',
            ];
        }

        try {
            $result = $this->proposeTool->execute($input);
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $id,
                'content'     => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            return [
                'type'   => 'tool_use',
                'id'     => $id,
                'name'   => $name,
                'input'  => $input,
                'result' => $result,
                'status' => 'pending',
            ];
        } catch (Throwable $e) {
            $toolResults[] = [
                'type'        => 'tool_result',
                'tool_use_id' => $id,
                'content'     => $e->getMessage(),
                'is_error'    => true,
            ];
            return [
                'type'   => 'tool_use',
                'id'     => $id,
                'name'   => $name,
                'input'  => $input,
                'error'  => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    /**
     * Decompose stored ChatMessage rows back into Anthropic's expected
     * shape (assistant tool_use and user tool_result are stored merged in
     * one of our rows; we split them again on the way out).
     *
     * @return list<array<string, mixed>>
     */
    private function buildAnthropicHistory(): array
    {
        $rows = ChatMessage::with('sender')
            ->orderByDesc('id')
            ->limit(self::HISTORY_WINDOW)
            ->get()
            ->reverse()
            ->values();

        $messages = [];
        foreach ($rows as $row) {
            if ($row->role === ChatMessage::ROLE_USER) {
                // Shared chat: prefix user content with "[Name]:" so the model
                // attributes prior messages correctly. Without this it sees
                // "user said X" without knowing which household member.
                $name = $row->sender?->name ?? 'unknown';
                $prefixed = array_map(
                    fn (array $b) => $b['type'] === 'text'
                        ? ['type' => 'text', 'text' => "[{$name}]: " . (string) $b['text']]
                        : $b,
                    $row->content,
                );
                $messages[] = [
                    'role'    => 'user',
                    'content' => $prefixed,
                ];
                continue;
            }

            // assistant: split into [assistant tool_use+text] then [user tool_result]
            //
            // Пропускаем tool_use со status='error' (модель сама сгенерила
            // невалидный call — нет target_user_uuid и т.п.). Anthropic/OpenRouter
            // иногда возвращает 403 forbidden ("Request not allowed") когда в
            // истории есть tool_use с неполным input. И семантически такие
            // блоки бесполезны — это шум, без него модель чище работает.
            $assistantBlocks = [];
            $toolResultBlocks = [];
            foreach ($row->content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $assistantBlocks[] = ['type' => 'text', 'text' => (string) $block['text']];
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    if (($block['status'] ?? '') === 'error') {
                        continue;
                    }
                    $assistantBlocks[] = [
                        'type'  => 'tool_use',
                        'id'    => $block['id'],
                        'name'  => $block['name'],
                        'input' => $block['input'],
                    ];
                    $resultStr = isset($block['result'])
                        ? json_encode($block['result'], JSON_UNESCAPED_UNICODE)
                        : (string) ($block['error'] ?? 'No result.');
                    $toolResultBlocks[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => $resultStr,
                        'is_error'    => isset($block['error']),
                    ];
                }
            }
            if ($assistantBlocks !== []) {
                $messages[] = ['role' => 'assistant', 'content' => $assistantBlocks];
            }
            if ($toolResultBlocks !== []) {
                $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];
            }
        }
        return $messages;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private static function withCacheControl(array $definition): array
    {
        $definition['cache_control'] = ['type' => 'ephemeral'];
        return $definition;
    }
}
