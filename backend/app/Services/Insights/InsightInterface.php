<?php

declare(strict_types=1);

namespace App\Services\Insights;

interface InsightInterface
{
    public function evaluate(InsightContext $ctx): ?Insight;

    public function priority(): int;
}
