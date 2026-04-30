<?php

declare(strict_types=1);

namespace App\Services\Days;

use App\Models\DayEntry;
use App\Models\User;
use App\Services\Goals\GoalResolver;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightEngine;
use App\Services\Modes\ModeClassifier;
use App\Services\Tdee\TdeeCalculator;
use Carbon\Carbon;

class DayAggregator
{
    /**
     * Used when the user has no logged measurements yet — TDEE still needs a
     * weight, so we use a generic placeholder. Surfaced in UI as a hint to log.
     */
    public const FALLBACK_WEIGHT_KG = 80.0;

    public static function forDate(User $user, Carbon $date): array
    {
        $entry = DayEntry::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->with(['meals', 'measurements', 'workouts'])
            ->first();

        $goal = GoalResolver::forDate($user, $date);

        // Latest weight on or before this date drives TDEE for the day.
        $latestWeight = \App\Models\Measurement::where('user_id', $user->id)
            ->where('measured_at', '<=', $date->endOfDay())
            ->orderByDesc('measured_at')
            ->value('weight_kg') ?? self::FALLBACK_WEIGHT_KG;

        $profile = $user->profile;

        $tdeeBreakdown = null;
        if ($profile) {
            $workoutsForTdee = $entry
                ? $entry->workouts->map(fn ($w) => ['kcal_burned' => $w->kcal_burned])->toArray()
                : [];
            $steps = $entry?->steps;
            $tdeeBreakdown = TdeeCalculator::compute($profile, $latestWeight, $steps, $workoutsForTdee);
        }

        $meals = $entry ? $entry->meals : collect();
        // Only the latest measurement per day is shown — older ones may exist as
        // legacy data but the canonical "weight today" is the most recent one.
        $measurements = $entry
            ? collect([$entry->measurements->sortByDesc('measured_at')->first()])->filter()->values()
            : collect();
        $workouts = $entry ? $entry->workouts : collect();

        $totals = [
            'kcal'      => round((float) $meals->sum('kcal'), 1),
            'protein_g' => round((float) $meals->sum('protein_g'), 1),
            'fat_g'     => round((float) $meals->sum('fat_g'), 1),
            'carbs_g'   => round((float) $meals->sum('carbs_g'), 1),
        ];

        // Mode now reflects plan execution (eaten vs goal), independent of TDEE.
        $mode = $goal ? ModeClassifier::classify($goal->kcal, (float) $totals['kcal']) : null;

        $now = Carbon::now($user->timezone ?? 'UTC');
        $hoursIntoDay = $date->isSameDay($now) ? (int) $now->hour : 23;

        $ctx = new InsightContext(
            user: $user,
            date: $date,
            dayEntry: $entry,
            goal: $goal,
            tdee: $tdeeBreakdown,
            mode: $mode,
            totals: $totals,
            meals: $meals,
            measurements: $measurements,
            workouts: $workouts,
            hoursIntoDay: $hoursIntoDay,
        );

        $insights = array_map(fn ($i) => $i->toArray(), InsightEngine::evaluate($ctx));

        return [
            'date'         => $date->toDateString(),
            'day_entry'    => $entry,
            'goal'         => $goal,
            'tdee'         => $tdeeBreakdown ? [
                'bmr'           => $tdeeBreakdown->bmr,
                'base_kcal'     => $tdeeBreakdown->baseKcal,
                'steps_kcal'    => $tdeeBreakdown->stepsKcal,
                'workouts_kcal' => $tdeeBreakdown->workoutsKcal,
                'total'         => $tdeeBreakdown->total,
            ] : null,
            'mode'         => $mode ? [
                'code'       => $mode->code,
                'label'      => $mode->label,
                'delta_kcal' => $mode->deltaKcal,
            ] : null,
            'totals'       => $totals,
            'meals'        => $meals,
            'measurements' => $measurements,
            'workouts'     => $workouts,
            'insights'     => $insights,
        ];
    }
}
