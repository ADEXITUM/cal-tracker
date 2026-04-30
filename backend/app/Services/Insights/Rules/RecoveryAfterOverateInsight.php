<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Models\DayEntry;
use App\Models\Goal;
use App\Services\Goals\GoalResolver;
use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class RecoveryAfterOverateInsight implements InsightInterface
{
    public const PRIORITY = 85;

    /** Show only in the morning — by afternoon the message is no longer actionable. */
    public const MORNING_CUTOFF_HOUR = 12;

    /** Yesterday must have exceeded goal by more than this for the message to fire. */
    public const EXCESS_THRESHOLD_KCAL = 300;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay > self::MORNING_CUTOFF_HOUR) return null;

        $yesterday = $ctx->date->copy()->subDay();
        $entry = DayEntry::where('user_id', $ctx->user->id)
            ->whereDate('date', $yesterday)
            ->with('meals')
            ->first();

        if (!$entry || $entry->meals->isEmpty()) return null;

        $yesterdayKcal = $entry->meals->sum('kcal');
        $yesterdayGoal = GoalResolver::forDate($ctx->user, $yesterday);
        if (!$yesterdayGoal instanceof Goal) return null;

        $excess = (int) round($yesterdayKcal - $yesterdayGoal->kcal);
        if ($excess <= self::EXCESS_THRESHOLD_KCAL) return null;

        return new Insight(
            code: 'recovery_after_overate',
            tone: 'warm',
            title: 'Вчера был перебор',
            body: "Вчера было +{$excess} от плана. Сегодня план тот же — не урезай больше, это контрпродуктивно. Просто продолжай",
        );
    }
}
