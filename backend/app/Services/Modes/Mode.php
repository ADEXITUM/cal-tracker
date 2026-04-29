<?php

declare(strict_types=1);

namespace App\Services\Modes;

readonly class Mode
{
    public function __construct(
        public string $code,
        public string $label,
        public int $deltaKcal,
    ) {}
}
