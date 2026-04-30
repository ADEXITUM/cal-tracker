<?php

declare(strict_types=1);

namespace App\Services\Modes;

/**
 * Classifies *daily plan execution* — how close eaten kcal is to the goal.
 * The goal type itself (cut/maintenance/bulk) is a separate user choice
 * stored on the goal, independent of this per-day classification.
 */
class ModeClassifier
{
    /** Within ±5% of goal — counts as on target. */
    public const ON_TARGET_PCT = 0.05;
    /** Within 5–15% deviation — moderate miss. */
    public const MODERATE_PCT = 0.15;

    public static function classify(int $goalKcal, float $eatenKcal): Mode
    {
        $delta = (int) round($eatenKcal - $goalKcal);
        $pct = $goalKcal > 0 ? abs($delta) / $goalKcal : 0;

        [$code, $label] = match (true) {
            $pct <= self::ON_TARGET_PCT => ['on_target', 'На цели'],
            $delta > 0 && $pct <= self::MODERATE_PCT => ['over', 'Перебор'],
            $delta > 0 => ['far_over', 'Сильный перебор'],
            $pct <= self::MODERATE_PCT => ['under', 'Недобор'],
            default => ['far_under', 'Сильный недобор'],
        };

        return new Mode(code: $code, label: $label, deltaKcal: $delta);
    }
}
