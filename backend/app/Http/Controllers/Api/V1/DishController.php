<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DishController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Dish::where('user_id', $request->user()->id)
            ->whereNull('archived_at')
            ->orderByDesc('usage_count')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%' . $request->search . '%');
        }

        $dishes = $query->limit((int) ($request->query('limit', 20)))->get();

        return response()->json(['data' => DishResource::collection($dishes)]);
    }

    public function store(DishRequest $request): JsonResponse
    {
        $dish = Dish::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new DishResource($dish)], 201);
    }

    public function update(DishRequest $request, string $uuid): JsonResponse
    {
        $dish = Dish::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $dish->update($request->validated());
        return response()->json(['data' => new DishResource($dish)]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $dish = Dish::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $dish->update(['archived_at' => now()]);
        return response()->json(null, 204);
    }
}
