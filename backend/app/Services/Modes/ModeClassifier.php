<?php

declare(strict_types=1);

namespace App\Services\Modes;

class ModeClassifier
{
    public static function classify(int $goalKcal, int $tdeeKcal): Mode
    {
        $delta = $goalKcal - $tdeeKcal;

        [$code, $label] = match (true) {
            $delta < -600          => ['extreme_cut', 'Экстрим-сушка'],
            $delta < -300          => ['cut', 'Сушка'],
            $delta < -100          => ['cut_lite', 'Лёгкая сушка'],
            $delta <= 100          => ['maintenance', 'Поддержка'],
            $delta <= 300          => ['light_bulk', 'Лёгкий набор'],
            default                => ['bulk', 'Набор'],
        };

        return new Mode(code: $code, label: $label, deltaKcal: $delta);
    }
}
