<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WorkoutRequest;
use App\Http\Resources\WorkoutResource;
use App\Models\Workout;
use App\Services\Meals\MealFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function store(WorkoutRequest $request, string $date): JsonResponse
    {
        $user  = $request->user();
        $entry = MealFactory::getOrCreateEntry($user, $date);

        $workout = Workout::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $user->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new WorkoutResource($workout)], 201);
    }

    public function update(WorkoutRequest $request, string $uuid): JsonResponse
    {
        $w = Workout::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $w->update($request->validated());
        return response()->json(['data' => new WorkoutResource($w)]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        Workout::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail()->delete();
        return response()->json(null, 204);
    }
}
