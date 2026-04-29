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
    public static function forDate(User $user, Carbon $date): array
    {
        $entry = DayEntry::where('user_id', $user->id)
            ->whereDate('date', $date)
            ->with(['meals', 'measurements', 'workouts'])
            ->first();

        $goal = GoalResolver::forDate($user, $date);

        // Latest weight for TDEE — look back up to 30 days
        $latestWeight = \App\Models\Measurement::where('user_id', $user->id)
            ->where('measured_at', '<=', $date->endOfDay())
            ->orderByDesc('measured_at')
            ->value('weight_kg') ?? 80.0;

        $profile = $user->profile;

        $tdeeBreakdown = null;
        $mode = null;
        if ($profile && $goal) {
            $workoutsForTdee = $entry
                ? $entry->workouts->map(fn ($w) => ['kcal_burned' => $w->kcal_burned])->toArray()
                : [];
            $steps = $entry?->steps;
            $tdeeBreakdown = TdeeCalculator::compute($profile, $latestWeight, $steps, $workoutsForTdee);
            $mode = ModeClassifier::classify($goal->kcal, $tdeeBreakdown->total);
        }

        $meals = $entry ? $entry->meals : collect();
        $measurements = $entry ? $entry->measurements : collect();
        $workouts = $entry ? $entry->workouts : collect();

        $totals = [
            'kcal'      => round((float) $meals->sum('kcal'), 1),
            'protein_g' => round((float) $meals->sum('protein_g'), 1),
            'fat_g'     => round((float) $meals->sum('fat_g'), 1),
            'carbs_g'   => round((float) $meals->sum('carbs_g'), 1),
        ];

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
                'activity_kcal' => $tdeeBreakdown->activityKcal,
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
