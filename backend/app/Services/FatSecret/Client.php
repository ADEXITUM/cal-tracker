<?php

declare(strict_types=1);

namespace App\Services\FatSecret;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Тонкая обёртка над FatSecret Platform REST API (https://platform.fatsecret.com/docs/guides).
 *
 *   - OAuth 2.0 client_credentials → access_token, scope=basic.
 *   - Токен короткоживущий (24ч по доке) — кэшируем в Laravel Cache до expires_in - 60s.
 *   - REST: GET https://platform.fatsecret.com/rest/server.api?method=...&format=json
 *     с Authorization: Bearer <token>.
 *
 * Возвращаем нормализованные данные (а не сырой ответ FatSecret), чтобы фронт не лез
 * в их специфический формат `servings.serving` / `food_id`.
 */
class Client
{
    /** Кэш-ключ для access_token. */
    public const TOKEN_CACHE_KEY = 'fatsecret.access_token';

    /** Сколько секунд держим запас перед истечением токена. */
    private const TOKEN_TTL_SAFETY = 60;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $tokenUrl,
        private readonly string $apiBase,
        private readonly string $scope,
    ) {}

    public static function fromConfig(): self
    {
        $clientId     = (string) config('services.fatsecret.client_id', '');
        $clientSecret = (string) config('services.fatsecret.client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('FATSECRET_CLIENT_ID / FATSECRET_CLIENT_SECRET не настроены.');
        }
        return new self(
            clientId: $clientId,
            clientSecret: $clientSecret,
            tokenUrl: (string) config('services.fatsecret.token_url'),
            apiBase: (string) config('services.fatsecret.api_base'),
            scope: (string) config('services.fatsecret.scope', 'basic'),
        );
    }

    /**
     * Поиск продуктов по строке. Возвращает максимум $maxResults записей.
     *
     * @return list<array{food_id: string, name: string, brand: string|null, description: string|null}>
     */
    public function searchFoods(string $query, int $maxResults = 20): array
    {
        $data = $this->call([
            'method'            => 'foods.search',
            'search_expression' => $query,
            'max_results'       => (string) max(1, min(50, $maxResults)),
        ]);

        $foods = $data['foods']['food'] ?? [];
        // FatSecret отдаёт один продукт как объект, несколько — массивом. Нормализуем.
        if (!array_is_list($foods)) {
            $foods = [$foods];
        }

        return array_values(array_map(static function (array $f): array {
            return [
                'food_id'     => (string) ($f['food_id'] ?? ''),
                'name'        => (string) ($f['food_name'] ?? ''),
                'brand'       => isset($f['brand_name']) ? (string) $f['brand_name'] : null,
                'description' => isset($f['food_description']) ? (string) $f['food_description'] : null,
            ];
        }, $foods));
    }

    /**
     * Полные данные продукта, включая все доступные порции, с нормализованной структурой.
     *
     * @return array{
     *     food_id: string,
     *     name: string,
     *     brand: string|null,
     *     servings: list<array{
     *         serving_id: string,
     *         description: string,
     *         metric_amount: float|null,
     *         metric_unit: string|null,
     *         grams: float|null,
     *         kcal: float,
     *         protein_g: float,
     *         fat_g: float,
     *         carbs_g: float
     *     }>
     * }
     */
    public function getFood(string $foodId): array
    {
        $data = $this->call([
            'method'  => 'food.get.v4',
            'food_id' => $foodId,
        ]);

        $food = $data['food'] ?? null;
        if (!is_array($food)) {
            throw new RuntimeException("FatSecret: food {$foodId} не найден.");
        }

        $servings = $food['servings']['serving'] ?? [];
        if (!array_is_list($servings)) {
            $servings = [$servings];
        }

        $normalized = [];
        foreach ($servings as $s) {
            if (!is_array($s)) {
                continue;
            }
            $grams = self::servingGrams($s);
            $normalized[] = [
                'serving_id'    => (string) ($s['serving_id'] ?? ''),
                'description'   => (string) ($s['serving_description'] ?? '—'),
                'metric_amount' => isset($s['metric_serving_amount']) ? (float) $s['metric_serving_amount'] : null,
                'metric_unit'   => isset($s['metric_serving_unit']) ? (string) $s['metric_serving_unit'] : null,
                'grams'         => $grams,
                'kcal'          => (float) ($s['calories'] ?? 0),
                'protein_g'     => (float) ($s['protein'] ?? 0),
                'fat_g'         => (float) ($s['fat'] ?? 0),
                'carbs_g'       => (float) ($s['carbohydrate'] ?? 0),
            ];
        }

        return [
            'food_id'  => (string) ($food['food_id'] ?? $foodId),
            'name'     => (string) ($food['food_name'] ?? ''),
            'brand'    => isset($food['brand_name']) ? (string) $food['brand_name'] : null,
            'servings' => $normalized,
        ];
    }

    /** Граммовый эквивалент порции, если FatSecret предоставил metric_serving_unit ∈ {g, ml}. */
    private static function servingGrams(array $serving): ?float
    {
        $unit = strtolower((string) ($serving['metric_serving_unit'] ?? ''));
        if ($unit !== 'g' && $unit !== 'ml') {
            return null;
        }
        $amount = (float) ($serving['metric_serving_amount'] ?? 0);
        return $amount > 0 ? $amount : null;
    }

    /**
     * Общий вызов REST-эндпоинта со свежим токеном. На 401 (истёкший токен) — один
     * раз перевыпускаем и повторяем.
     *
     * @param  array<string, string>  $params
     * @return array<string, mixed>
     */
    private function call(array $params): array
    {
        $params['format'] = 'json';

        $attempt = function (string $token) use ($params): \Illuminate\Http\Client\Response {
            return Http::withToken($token)
                ->asForm()
                ->timeout(15)
                // throw: false — иначе ретраер бросает RequestException на любой
                // failed-ответ (вкл. 401), и наш собственный 401-retry-токен
                // никогда не срабатывает.
                ->retry(2, 400, fn ($e) => $this->isRetryableTransport($e), throw: false)
                ->get($this->apiBase, $params);
        };

        $token = $this->accessToken();
        $response = $attempt($token);

        if ($response->status() === 401) {
            // Токен внезапно невалиден — выкидываем из кэша и пытаемся ещё раз.
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $attempt($this->accessToken());
        }

        if ($response->failed()) {
            throw new RuntimeException("FatSecret HTTP {$response->status()}: " . $response->body());
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new RuntimeException('FatSecret: пустой/невалидный JSON.');
        }
        // FatSecret 200-OK с error-конвертом — например, code 21 «IP not allowed».
        if (isset($data['error']) && is_array($data['error'])) {
            $code = (int) ($data['error']['code'] ?? 0);
            $msg  = (string) ($data['error']['message'] ?? 'unknown');
            throw new RuntimeException("FatSecret error {$code}: {$msg}");
        }
        return $data;
    }

    private function accessToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->timeout(15)
            ->retry(2, 400, fn ($e) => $this->isRetryableTransport($e), throw: false)
            ->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'scope'      => $this->scope,
            ]);

        if ($response->failed()) {
            throw new RuntimeException("FatSecret token HTTP {$response->status()}: " . $response->body());
        }

        $body = $response->json();
        $token = is_array($body) ? (string) ($body['access_token'] ?? '') : '';
        if ($token === '') {
            throw new RuntimeException('FatSecret token: пустой access_token в ответе.');
        }
        // expires_in — секунды; кэшируем чуть короче, чтобы не словить 401 на границе.
        $ttl = max(60, (int) ($body['expires_in'] ?? 3600) - self::TOKEN_TTL_SAFETY);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    private function isRetryableTransport(\Throwable $e): bool
    {
        if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
            return true;
        }
        if ($e instanceof ConnectionException) {
            return true;
        }
        if ($e instanceof RequestException) {
            return $e->response->status() >= 500;
        }
        return false;
    }
}
