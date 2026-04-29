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
    public function priority(): int { return 85; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay > 12) return null;

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
        if ($excess <= 300) return null;

        return new Insight(
            code: 'recovery_after_overate',
            tone: 'warm',
            title: 'Вчера был перебор',
            body: "Вчера было +{$excess} от плана. Сегодня план тот же — не урезай больше, это контрпродуктивно. Просто продолжай",
        );
    }
}
