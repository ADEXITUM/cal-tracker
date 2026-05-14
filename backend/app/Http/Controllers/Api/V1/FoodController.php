<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FatSecret\Client as FatSecretClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Прокси над FatSecret. Фронт не знает client_secret и не ходит на их домен.
 *
 * Не кэшируем сами результаты — это личный self-hosted трекер, объём запросов
 * мал, а свежесть выдачи важнее экономии лимита.
 */
class FoodController extends Controller
{
    public function search(Request $request, FatSecretClient $client): JsonResponse
    {
        $validated = $request->validate([
            'q'           => ['required', 'string', 'min:2', 'max:120'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $hits = $client->searchFoods(
                query: $validated['q'],
                maxResults: (int) ($validated['max_results'] ?? 20),
            );
        } catch (RuntimeException $e) {
            Log::warning('FatSecret search failed', ['q' => $validated['q'], 'error' => $e->getMessage()]);
            return response()->json(['message' => 'FatSecret недоступен', 'error' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $hits]);
    }

    public function show(string $foodId, FatSecretClient $client): JsonResponse
    {
        // food_id в FatSecret — десятичная строка; режем мусор на всякий случай.
        if (!preg_match('/^\d{1,20}$/', $foodId)) {
            return response()->json(['message' => 'Невалидный food_id'], 422);
        }

        try {
            $food = $client->getFood($foodId);
        } catch (RuntimeException $e) {
            Log::warning('FatSecret food.get failed', ['food_id' => $foodId, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'FatSecret недоступен', 'error' => $e->getMessage()], 502);
        }

        return response()->json(['data' => $food]);
    }
}
