<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Stats\StatsAggregator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = Carbon::parse($request->query('from'), $user->timezone);
        $to   = Carbon::parse($request->query('to'), $user->timezone);

        return response()->json([
            'data' => StatsAggregator::summary($user, $from, $to),
        ]);
    }

    public function series(Request $request): JsonResponse
    {
        $request->validate([
            'metric' => ['required', 'in:weight,body_fat_pct,muscle_mass_kg,body_water_pct,kcal,protein_g,fat_g,carbs_g,steps'],
            'from'   => ['required', 'date'],
            'to'     => ['required', 'date', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = Carbon::parse($request->query('from'), $user->timezone);
        $to   = Carbon::parse($request->query('to'), $user->timezone);

        return response()->json([
            'data' => StatsAggregator::series($user, (string) $request->query('metric'), $from, $to),
        ]);
    }
}
