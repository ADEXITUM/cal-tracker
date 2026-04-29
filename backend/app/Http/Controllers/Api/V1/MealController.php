<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MealRequest;
use App\Http\Resources\MealResource;
use App\Models\Dish;
use App\Models\Meal;
use App\Services\Meals\MealFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function store(MealRequest $request, string $date): JsonResponse
    {
        $user  = $request->user();
        $entry = MealFactory::getOrCreateEntry($user, $date);
        $slot  = $request->slot;
        $eatenAt = Carbon::parse($request->eaten_at);

        if ($request->filled('dish_uuid')) {
            $dish = Dish::where('uuid', $request->dish_uuid)
                ->where('user_id', $user->id)
                ->firstOrFail();
            $meal = MealFactory::fromDish($entry, $user, $dish, (float) $request->grams, $slot, $eatenAt);
        } else {
            $meal = MealFactory::fromAdHoc(
                $entry, $user,
                $request->name,
                $slot, $eatenAt,
                (float) $request->kcal,
                (float) $request->protein_g,
                (float) $request->fat_g,
                (float) $request->carbs_g,
            );
        }

        return response()->json(['data' => new MealResource($meal->load('dish'))], 201);
    }

    public function update(MealRequest $request, string $uuid): JsonResponse
    {
        $meal = Meal::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();

        if ($request->filled('dish_uuid')) {
            $dish = Dish::where('uuid', $request->dish_uuid)->where('user_id', $request->user()->id)->firstOrFail();
            $factor = (float) $request->grams / 100;
            $meal->update([
                'dish_id'   => $dish->id,
                'grams'     => $request->grams,
                'name'      => $dish->name,
                'slot'      => $request->slot,
                'eaten_at'  => $request->eaten_at,
                'kcal'      => round($dish->kcal_per_100g * $factor, 2),
                'protein_g' => round($dish->protein_per_100g * $factor, 2),
                'fat_g'     => round($dish->fat_per_100g * $factor, 2),
                'carbs_g'   => round($dish->carbs_per_100g * $factor, 2),
            ]);
        } else {
            $meal->update([
                'dish_id'   => null,
                'grams'     => null,
                'name'      => $request->name,
                'slot'      => $request->slot,
                'eaten_at'  => $request->eaten_at,
                'kcal'      => $request->kcal,
                'protein_g' => $request->protein_g,
                'fat_g'     => $request->fat_g,
                'carbs_g'   => $request->carbs_g,
            ]);
        }

        return response()->json(['data' => new MealResource($meal->load('dish'))]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        Meal::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail()->delete();
        return response()->json(null, 204);
    }
}
