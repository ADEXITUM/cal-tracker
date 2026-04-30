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

        // The client may send measured_at as "now", but the canonical timestamp
        // for stats grouping must match the day the user is editing. Anchor it
        // to noon of that date so it always falls inside the day in any tz.
        $payload = $request->validated();
        $payload['measured_at'] = \Carbon\Carbon::parse($date . ' 12:00:00', $user->timezone ?? 'UTC');

        $existing = Measurement::where('user_id', $user->id)
            ->where('day_entry_id', $entry->id)
            ->first();

        if ($existing) {
            $existing->update($payload);
            return response()->json(['data' => new MeasurementResource($existing)]);
        }

        $measurement = Measurement::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $user->id,
            ...$payload,
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
