<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class OnlyBreakfastInsight implements InsightInterface
{
    public function priority(): int { return 50; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if ($ctx->meals->isEmpty()) return null;
        if ($ctx->hoursIntoDay < 13) return null;
        if (!$ctx->isToday()) return null;

        $allBreakfast = $ctx->meals->every(fn ($m) => $m->slot === 'breakfast');
        if (!$allBreakfast) return null;

        return new Insight(
            code: 'only_breakfast',
            tone: 'neutral',
            title: 'Только завтрак',
            body: 'Записан только завтрак. Не забудь обед',
        );
    }
}
