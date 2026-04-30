<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MeasurementRequest;
use App\Http\Resources\MeasurementResource;
use App\Models\DayEntry;
use App\Models\Measurement;
use App\Services\Meals\MealFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function store(MeasurementRequest $request, string $date): JsonResponse
    {
        $user  = $request->user();
        $entry = MealFactory::getOrCreateEntry($user, $date);

        // Upsert: one measurement per day per user (overwrite same-day entry)
        $existing = Measurement::where('user_id', $user->id)
            ->where('day_entry_id', $entry->id)
            ->first();

        if ($existing) {
            $existing->update($request->validated());
            return response()->json(['data' => new MeasurementResource($existing)]);
        }

        $measurement = Measurement::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $user->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new MeasurementResource($measurement)], 201);
    }

    public function update(MeasurementRequest $request, string $uuid): JsonResponse
    {
        $m = Measurement::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail();
        $m->update($request->validated());
        return response()->json(['data' => new MeasurementResource($m)]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        Measurement::where('uuid', $uuid)->where('user_id', $request->user()->id)->firstOrFail()->delete();
        return response()->json(null, 204);
    }
}
