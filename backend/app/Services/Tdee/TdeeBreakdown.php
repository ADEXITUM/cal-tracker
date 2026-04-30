<?php

declare(strict_types=1);

namespace App\Services\Tdee;

readonly class TdeeBreakdown
{
    public function __construct(
        public int $bmr,
        public int $baseKcal,
        public int $stepsKcal,
        public int $workoutsKcal,
        public int $total,
    ) {}
}
