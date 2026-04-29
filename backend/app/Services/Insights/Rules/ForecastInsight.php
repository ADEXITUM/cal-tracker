<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Models\Meal;
use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class ForecastInsight implements InsightInterface
{
    public function priority(): int { return 70; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay < 14 || $ctx->hoursIntoDay > 22) return null;
        if ($ctx->meals->count() < 2) return null;

        // Average meal kcal over last 30 days
        $avgMealKcal = Meal::where('user_id', $ctx->user->id)
            ->where('eaten_at', '>=', $ctx->date->copy()->subDays(30))
            ->avg('kcal') ?? 0;

        if ($avgMealKcal <= 0) return null;

        $remainingMeals = max(0, 4 - $ctx->meals->count());
        $forecast = (int) round($ctx->totals['kcal'] + $remainingMeals * $avgMealKcal);
        $diff = $forecast - $ctx->goal->kcal;

        if (abs($diff) <= 100) {
            return new Insight(
                code: 'forecast',
                tone: 'good',
                title: 'Идёшь по плану',
                body: "Прогноз дня: ~{$forecast} ккал",
            );
        }

        if ($diff > 100) {
            return new Insight(
                code: 'forecast',
                tone: 'warm',
                title: 'Превышение по темпу',
                body: "По темпу к концу дня ~{$forecast} (+{$diff}). Можно сократить ужин или добавить активность",
            );
        }

        if ($diff < -200) {
            $under = abs($diff);
            return new Insight(
                code: 'forecast',
                tone: 'warm',
                title: 'Большой недобор',
                body: "По темпу к концу дня ~{$forecast} (−{$under}). Не урезай ниже цели — это контрпродуктивно",
            );
        }

        return null;
    }
}
