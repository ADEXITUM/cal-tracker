<?php

declare(strict_types=1);

namespace App\Services\Insights\Rules;

use App\Models\Measurement;
use App\Services\Insights\Insight;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightInterface;

class WeightTrendInsight implements InsightInterface
{
    public function priority(): int { return 30; }

    public function evaluate(InsightContext $ctx): ?Insight
    {
        if (!$ctx->goal) return null;
        if (!$ctx->isToday()) return null;

        $startedAt = $ctx->goal->start_date;
        $daysSinceStart = (int) abs($startedAt->diffInDays($ctx->date));
        if ($daysSinceStart < 14) return null;

        $measurements = Measurement::where('user_id', $ctx->user->id)
            ->where('measured_at', '>=', $ctx->date->copy()->subDays(30))
            ->orderBy('measured_at')
            ->get();

        if ($measurements->count() < 5) return null;

        // Linear regression slope (kg per day) on last up-to-14 measurements
        $latest = $measurements->take(-14);
        $n = $latest->count();
        $first = $latest->first();
        if (!$first) return null;
        $baseTs = $first->measured_at->timestamp;

        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($latest as $m) {
            $x = ($m->measured_at->timestamp - $baseTs) / 86400; // days
            $y = (float) $m->weight_kg;
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
        }
        $denom = $n * $sumXX - $sumX * $sumX;
        if ($denom == 0.0) return null;
        $slopePerDay = ($n * $sumXY - $sumX * $sumY) / $denom;
        $kgPerWeek = round($slopePerDay * 7, 2);

        $goalType = $ctx->goal->type;
        $isCut = $goalType === 'cut';
        $isBulk = $goalType === 'bulk';

        $assessment = '';
        if ($isCut) {
            if ($kgPerWeek <= -1) $assessment = 'Быстрее безопасного — стоит добавить ккал';
            elseif ($kgPerWeek >= -0.2) $assessment = 'Прогресс замедлился — возможно адаптация';
            elseif ($kgPerWeek <= -0.5) $assessment = 'Безопасная скорость';
            else $assessment = 'В рамках нормы';
        } elseif ($isBulk) {
            if ($kgPerWeek >= 0.7) $assessment = 'Слишком быстро — много жира будет';
            elseif ($kgPerWeek >= 0.25 && $kgPerWeek <= 0.5) $assessment = 'Lean bulk темп';
            else $assessment = 'В рамках нормы';
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
