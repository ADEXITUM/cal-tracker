<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Models\Meal;
use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class ForecastInsight implements InsightInterface
{
    public const PRIORITY = 70;

    /** Active hours window — too early = not enough signal, too late = day is essentially over. */
    public const WINDOW_START_HOUR = 14;
    public const WINDOW_END_HOUR   = 22;

    /** Need at least this many meals to project a trajectory. */
    public const MIN_MEALS = 2;

    /** Lookback window for "average meal kcal" baseline. */
    public const AVG_MEAL_LOOKBACK_DAYS = 30;

    /** Assumed typical meals/day so we can fill in remaining-meal estimate. */
    public const TYPICAL_MEALS_PER_DAY = 4;

    /** Forecast vs goal: ±this many kcal counts as "on plan". */
    public const ON_PLAN_BAND = 100;

    /** Under-forecast worse than this — flag big deficit risk. */
    public const FAR_UNDER_BAND = 200;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay < self::WINDOW_START_HOUR || $ctx->hoursIntoDay > self::WINDOW_END_HOUR) return null;
        if ($ctx->meals->count() < self::MIN_MEALS) return null;

        $avgMealKcal = Meal::where('user_id', $ctx->user->id)
            ->where('eaten_at', '>=', $ctx->date->copy()->subDays(self::AVG_MEAL_LOOKBACK_DAYS))
            ->avg('kcal') ?? 0;

        if ($avgMealKcal <= 0) return null;

        $remainingMeals = max(0, self::TYPICAL_MEALS_PER_DAY - $ctx->meals->count());
        $forecast = (int) round($ctx->totals['kcal'] + $remainingMeals * $avgMealKcal);
        $diff = $forecast - $ctx->goal->kcal;

        if (abs($diff) <= self::ON_PLAN_BAND) {
            return new Insight(
                code: 'forecast',
                tone: 'good',
                title: 'Идёшь по плану',
                body: "Прогноз дня: ~{$forecast} ккал",
            );
        }

        if ($diff > self::ON_PLAN_BAND) {
            return new Insight(
                code: 'forecast',
                tone: 'warm',
                title: 'Превышение по темпу',
                body: "По темпу к концу дня ~{$forecast} (+{$diff}). Можно сократить ужин или добавить активность",
            );
        }

        if ($diff < -self::FAR_UNDER_BAND) {
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
