<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * "Логический" день в дневнике питания: всё что съедено до 3 утра по
 * локальному времени пользователя относится к предыдущему календарному дню.
 * Это match-ит сценарий ночного жора: в 02:30 это ещё "сегодняшний обед",
 * который мы не успели закрыть, а не "завтрак завтрашнего дня".
 *
 * Используется чат-помощником (system-prompt "сегодня" + куда писать meal)
 * и должен быть в синхроне с frontend-овой версией этой логики
 * (lib/time.ts: LOGICAL_DAY_CUTOFF_HOUR).
 */
final class LogicalDate
{
    public const CUTOFF_HOUR = 3;

    /**
     * Логическая "сегодня" для пользователя в заданной таймзоне.
     */
    public static function today(?string $timezone): CarbonImmutable
    {
        return self::forInstant(CarbonImmutable::now($timezone ?: 'UTC'), $timezone);
    }

    /**
     * Логическая дата конкретного момента времени.
     */
    public static function forInstant(CarbonInterface $instant, ?string $timezone): CarbonImmutable
    {
        $local = CarbonImmutable::instance($instant)->setTimezone($timezone ?: 'UTC');
        if ((int) $local->format('H') < self::CUTOFF_HOUR) {
            $local = $local->subDay();
        }
        return $local->startOfDay();
    }
}
