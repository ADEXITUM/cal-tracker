<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class EmptyDayInsight implements InsightInterface
{
    public const PRIORITY = 90;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if ($ctx->meals->isNotEmpty() || $ctx->measurements->isNotEmpty()) return null;

        return new Insight(
            code: 'empty_day',
            tone: 'neutral',
            title: $ctx->isPast() ? 'Не было замеров' : 'Пустой день',
            body: $ctx->isPast()
                ? 'Запиши хотя бы вес — тренд важнее идеала'
                : 'Запиши вес или приём чтобы отслеживать день',
        );
    }
}
