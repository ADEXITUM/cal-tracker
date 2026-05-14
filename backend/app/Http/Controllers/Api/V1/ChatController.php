<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApplyChatProposalRequest;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Services\Chat\ChatOrchestrator;
use App\Services\Chat\ProposalApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Chat is shared across all admins — no per-user scoping.
        $limit = min(200, max(1, (int) $request->query('limit', 100)));
        $messages = ChatMessage::with('sender')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'data' => ChatMessageResource::collection($messages),
        ]);
    }

    public function store(SendChatMessageRequest $request, ChatOrchestrator $orchestrator): JsonResponse
    {
        try {
            [$userMsg, $assistantMsg] = $orchestrator->turn(
                $request->user(),
                (string) $request->validated()['text'],
            );
        } catch (Throwable $e) {
            // LLM-провайдер недоступен (Anthropic/OpenRouter 4xx/5xx, таймаут и т.п.).
            // Сообщение пользователя уже сохранено в БД до вызова Anthropic — так что
            // оно не теряется. Возвращаем 503 с понятным текстом, чтобы фронт показал
            // юзеру, что произошло, и не было голого "Server Error" 500.
            Log::warning('Chat upstream failed', [
                'user_id' => $request->user()?->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'AI временно недоступен. Сообщение сохранено — попробуй ещё раз через минуту.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'user'      => new ChatMessageResource($userMsg->load('sender')),
                'assistant' => new ChatMessageResource($assistantMsg->load('sender')),
            ],
        ], 201);
    }

    public function apply(
        ApplyChatProposalRequest $request,
        ProposalApplier $applier,
        string $uuid,
    ): JsonResponse {
        // Admin role is enforced upstream by the route middleware, and the
        // chat is shared between admins — any admin can act on any message.
        $message = ChatMessage::where('uuid', $uuid)->firstOrFail();
        $updated = $applier->apply($message, $request->validated()['items']);

        return response()->json([
            'data' => new ChatMessageResource($updated->load('sender')),
        ]);
    }
}
