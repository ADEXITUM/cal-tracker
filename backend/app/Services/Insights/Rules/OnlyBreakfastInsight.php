<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class OnlyBreakfastInsight implements InsightInterface
{
    public const PRIORITY = 50;

    /** Only nudge after this hour — earlier it's normal to have just breakfast. */
    public const REMINDER_AFTER_HOUR = 13;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if ($ctx->meals->isEmpty()) return null;
        if ($ctx->hoursIntoDay < self::REMINDER_AFTER_HOUR) return null;
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
