<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;
use App\Support\Numbers;

class EndOfDayDeficitInsight implements InsightInterface
{
    public const PRIORITY = 80;

    /** Hour of day from which we consider the day "essentially over". */
    public const END_OF_DAY_HOUR = 22;

    /** Within ±this many kcal of goal — counts as "по плану" at end of day. */
    public const ON_TRACK_KCAL_BAND = 200;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay < self::END_OF_DAY_HOUR) return null;

        $diff = (int) round($ctx->totals['kcal'] - $ctx->goal->kcal);

        if (abs($diff) <= self::ON_TRACK_KCAL_BAND) {
            return new Insight(
                code: 'end_of_day',
                tone: 'good',
                title: 'День завершён',
                body: "Дефицит дня: {$diff}. По плану",
            );
        }

        if ($diff > self::ON_TRACK_KCAL_BAND) {
            $monthly = $diff * Numbers::DAYS_PER_MONTH;
            return new Insight(
                code: 'end_of_day',
                tone: 'warm',
                title: 'Перебор',
                body: "Сегодня +{$diff} от плана. Это {$monthly} ккал к месяцу. Завтра — план тот же, не урезай",
            );
        }

        // diff < -ON_TRACK_KCAL_BAND
        $under = abs($diff);
        return new Insight(
            code: 'end_of_day',
            tone: 'neutral',
            title: 'Большой дефицит',
            body: "Сегодня большой дефицит {$under}. Если намеренно — ок. Если случайно — стоит добавить",
        );
    }
}
