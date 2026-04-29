<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class EndOfDayDeficitInsight implements InsightInterface
{
    public function priority(): int { return 80; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay < 22) return null;

        $diff = (int) round($ctx->totals['kcal'] - $ctx->goal->kcal);

        if (abs($diff) <= 200) {
            return new Insight(
                code: 'end_of_day',
                tone: 'good',
                title: 'День завершён',
                body: "Дефицит дня: {$diff}. По плану",
            );
        }

        if ($diff > 200) {
            $monthly = $diff * 30;
            return new Insight(
                code: 'end_of_day',
                tone: 'warm',
                title: 'Перебор',
                body: "Сегодня +{$diff} от плана. Это {$monthly} ккал к месяцу. Завтра — план тот же, не урезай",
            );
        }

        // diff < -200
        $under = abs($diff);
        return new Insight(
            code: 'end_of_day',
            tone: 'neutral',
            title: 'Большой дефицит',
            body: "Сегодня большой дефицит {$under}. Если намеренно — ок. Если случайно — стоит добавить",
        );
    }
}
