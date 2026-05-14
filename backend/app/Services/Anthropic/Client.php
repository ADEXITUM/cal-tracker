<?php

declare(strict_types=1);

namespace App\Services\Anthropic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class Client
{
    public const AUTH_ANTHROPIC = 'anthropic';
    public const AUTH_BEARER    = 'bearer';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiBase,
        private readonly string $authStyle,
        private readonly string $model,
        private readonly int $maxTokens,
        private readonly string $apiVersion,
    ) {}

    public static function fromConfig(): self
    {
        $apiKey = (string) config('services.anthropic.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not configured.');
        }
        return new self(
            apiKey: $apiKey,
            apiBase: (string) config('services.anthropic.api_base'),
            authStyle: (string) config('services.anthropic.auth_style'),
            model: (string) config('services.anthropic.model'),
            maxTokens: (int) config('services.anthropic.max_tokens'),
            apiVersion: (string) config('services.anthropic.version'),
        );
    }

    /**
     * Single call to the Messages API. Works against:
     *   - api.anthropic.com (auth_style = anthropic, x-api-key header)
     *   - openrouter.ai/api (auth_style = bearer, Authorization header)
     * Both speak the same Anthropic-shape body, so only headers + base URL vary.
     *
     * @param  list<array<string, mixed>>  $system   system content blocks (text, cache_control)
     * @param  list<array<string, mixed>>  $tools    tool definitions
     * @param  list<array<string, mixed>>  $messages conversation in Anthropic shape
     * @return array{content: list<array<string, mixed>>, stop_reason: string, usage: array<string, int>}
     */
    public function messages(array $system, array $tools, array $messages): array
    {
        $headers = ['content-type' => 'application/json'];
        if ($this->authStyle === self::AUTH_BEARER) {
            $headers['authorization'] = 'Bearer ' . $this->apiKey;
        } else {
            $headers['x-api-key']         = $this->apiKey;
            $headers['anthropic-version'] = $this->apiVersion;
        }

        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $system,
            'tools'      => $tools,
            'messages'   => $messages,
        ];

        // OpenRouter маршрутизирует запросы Anthropic-моделей через нескольких
        // апстримов (Anthropic direct, Bedrock, Vertex). Часть из них для нашего
        // аккаунта периодически возвращает "Access to Anthropic models is..." —
        // прибиваем routing к самому Anthropic, без fallback'ов. Параметр
        // игнорируется при прямом обращении к api.anthropic.com, но мы посылаем
        // его только для bearer-стиля (=OpenRouter), чтобы не было лишнего шума.
        if ($this->authStyle === self::AUTH_BEARER) {
            $body['provider'] = [
                'order'           => ['anthropic'],
                'allow_fallbacks' => false,
            ];
        }

        // Окно полного запроса ограничено ~90s (45 * 2 попытки), чтобы мобильный
        // Chrome успел дождаться ответа, не порвав соединение (default network
        // timeout ~60-120s на сотовой сети). Иначе backend продолжает считать,
        // а юзер уже видит "failed to fetch" и шлёт повторно.
        $response = Http::withHeaders($headers)
            ->timeout(45)
            ->retry(2, 1000, fn ($exception, $request) => $this->isRetryable($exception))
            ->post($this->apiBase . '/v1/messages', $body);

        if ($response->failed()) {
            // Полное тело ошибки идёт отдельной записью — иначе ExceptionFormatter
            // обрезает на ~256 символов, и в логе видно только "Provider returned
            // error... (truncated)" без сути проблемы (тип ошибки, какой апстрим).
            Log::error('Anthropic upstream raw error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException(
                "Anthropic API error {$response->status()}: " . $response->body(),
            );
        }

        $data = $response->json();
        return [
            'content'     => $data['content'] ?? [],
            'stop_reason' => $data['stop_reason'] ?? 'end_turn',
            'usage'       => $data['usage'] ?? [],
        ];
    }

    private function isRetryable(\Throwable $e): bool
    {
        // Anything transport-level (DNS failure, TCP/TLS issues, timeouts).
        // Both Guzzle's native ConnectException AND Laravel's wrapped
        // ConnectionException can show up depending on call path, so check both.
        if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
            return true;
        }
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }
        // Upstream 5xx is worth retrying; 4xx (auth, validation) is not.
        if ($e instanceof \Illuminate\Http\Client\RequestException) {
            return $e->response->status() >= 500;
        }
        return false;
    }
}
