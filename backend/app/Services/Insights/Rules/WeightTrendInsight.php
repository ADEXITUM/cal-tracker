<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Models\Measurement;
use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;
use App\Support\Numbers;

class WeightTrendInsight implements InsightInterface
{
    public const PRIORITY = 30;

    /** Goal must be at least this old — otherwise the trend is just noise. */
    public const MIN_GOAL_AGE_DAYS = 14;

    /** Lookback window for measurements feeding the regression. */
    public const LOOKBACK_DAYS = 30;

    /** Need this many measurements to fit a meaningful slope. */
    public const MIN_MEASUREMENTS = 5;

    /** Use the most recent N measurements (≈ 2 weeks of near-daily weigh-ins). */
    public const REGRESSION_WINDOW = 14;

    /**
     * Cut (deficit) safe-rate thresholds — kg/week.
     * Negative values; "<=" means "at least this fast in the losing direction".
     */
    public const CUT_TOO_FAST_KG_PER_WEEK    = -1.0;  // losing >1 kg/wk → too aggressive
    public const CUT_SAFE_KG_PER_WEEK        = -0.5;  // 0.5–1.0 kg/wk = safe
    public const CUT_STALLED_KG_PER_WEEK     = -0.2;  // slower than 0.2 kg/wk → likely adapted

    /** Bulk thresholds — kg/week. */
    public const BULK_TOO_FAST_KG_PER_WEEK   = 0.7;   // > 0.7 kg/wk = too much fat gain
    public const BULK_LEAN_MIN_KG_PER_WEEK   = 0.25;
    public const BULK_LEAN_MAX_KG_PER_WEEK   = 0.5;

    public function priority(): int { return self::PRIORITY; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;

        $startedAt = $ctx->goal->start_date;
        $daysSinceStart = (int) abs($startedAt->diffInDays($ctx->date));
        if ($daysSinceStart < self::MIN_GOAL_AGE_DAYS) return null;

        $measurements = Measurement::where('user_id', $ctx->user->id)
            ->where('measured_at', '>=', $ctx->date->copy()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('measured_at')
            ->get();

        if ($measurements->count() < self::MIN_MEASUREMENTS) return null;

        // Linear regression on the most recent window.
        $latest = $measurements->take(-self::REGRESSION_WINDOW);
        $n = $latest->count();
        $first = $latest->first();
        if (!$first) return null;
        $baseTs = $first->measured_at->timestamp;

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($latest as $m) {
            $x = ($m->measured_at->timestamp - $baseTs) / Numbers::SECONDS_PER_DAY;
            $y = (float) $m->weight_kg;
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
        }
        $denom = $n * $sumXX - $sumX * $sumX;
        if ($denom == 0.0) return null;
        $slopePerDay = ($n * $sumXY - $sumX * $sumY) / $denom;
        $kgPerWeek = round($slopePerDay * Numbers::DAYS_PER_WEEK, 2);

        $goalType = $ctx->goal->type;

        if ($goalType === 'cut') {
            if ($kgPerWeek <= self::CUT_TOO_FAST_KG_PER_WEEK) {
                $assessment = 'Быстрее безопасного — стоит добавить ккал';
            } elseif ($kgPerWeek >= self::CUT_STALLED_KG_PER_WEEK) {
                $assessment = 'Прогресс замедлился — возможно адаптация';
            } elseif ($kgPerWeek <= self::CUT_SAFE_KG_PER_WEEK) {
                $assessment = 'Безопасная скорость';
            } else {
                $assessment = 'В рамках нормы';
            }
        } elseif ($goalType === 'bulk') {
            if ($kgPerWeek >= self::BULK_TOO_FAST_KG_PER_WEEK) {
                $assessment = 'Слишком быстро — много жира будет';
            } elseif ($kgPerWeek >= self::BULK_LEAN_MIN_KG_PER_WEEK && $kgPerWeek <= self::BULK_LEAN_MAX_KG_PER_WEEK) {
                $assessment = 'Lean bulk темп';
            } else {
                $assessment = 'В рамках нормы';
            }
        } else {
            return null;
        }

        $sign = $kgPerWeek > 0 ? '+' : '';
        return new Insight(
            code: 'weight_trend',
            tone: 'neutral',
            title: 'Темп за 2 недели',
            body: "{$sign}{$kgPerWeek} кг/нед. {$assessment}",
        );
    }
}
