<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Services\Insights\Rules\EmptyDayInsight;
use App\Services\Insights\Rules\EndOfDayDeficitInsight;
use App\Services\Insights\Rules\ForecastInsight;
use App\Services\Insights\Rules\KcalRemainingInsight;
use App\Services\Insights\Rules\OnlyBreakfastInsight;
use App\Services\Insights\Rules\RecoveryAfterOverateInsight;
use App\Services\Insights\Rules\WeightTrendInsight;

class InsightEngine
{
    /** @return list<InsightInterface> */
    public static function rules(): array
    {
        return [
            new EmptyDayInsight(),
            new OnlyBreakfastInsight(),
            new KcalRemainingInsight(),
            new ForecastInsight(),
            new EndOfDayDeficitInsight(),
            new RecoveryAfterOverateInsight(),
            new WeightTrendInsight(),
        ];
    }

    /** @return list<Insight> */
    public static function evaluate(InsightContext $ctx, int $maxResults = 2): array
    {
        $insights = [];
        foreach (self::rules() as $rule) {
            $result = $rule->evaluate($ctx);
            if ($result !== null) {
                $insights[] = ['priority' => $rule->priority(), 'insight' => $result];
            }
        }
        usort($insights, fn ($a, $b) => $b['priority'] - $a['priority']);
        return array_slice(array_map(fn ($e) => $e['insight'], $insights), 0, $maxResults);
    }
}
