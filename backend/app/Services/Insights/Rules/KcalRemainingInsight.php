<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class KcalRemainingInsight implements InsightInterface
{
    public function priority(): int { return 60; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;
        if ($ctx->hoursIntoDay < 10 || $ctx->hoursIntoDay > 22) return null;
        if ($ctx->totals['kcal'] >= $ctx->goal->kcal) return null;

        $remainingKcal = (int) round($ctx->goal->kcal - $ctx->totals['kcal']);
        $remainingP = (int) round($ctx->goal->protein_g - $ctx->totals['protein_g']);
        $remainingF = (int) round($ctx->goal->fat_g - $ctx->totals['fat_g']);
        $remainingC = (int) round($ctx->goal->carbs_g - $ctx->totals['carbs_g']);

        return new Insight(
            code: 'kcal_remaining',
            tone: 'neutral',
            title: 'Осталось на день',
            body: "{$remainingKcal} ккал · Б {$remainingP} · Ж {$remainingF} · У {$remainingC}",
        );
    }
}
