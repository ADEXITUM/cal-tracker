<?php

declare(strict_types=1);

namespace App\Services\Stats;

use App\Models\DayEntry;
use App\Models\Measurement;
use App\Models\User;
use App\Services\Goals\GoalResolver;
use Carbon\Carbon;

class StatsAggregator
{
    /** @return array<string, mixed> */
    public static function summary(User $user, Carbon $from, Carbon $to): array
    {
        $measurements = Measurement::where('user_id', $user->id)
            ->whereBetween('measured_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('measured_at')
            ->get();

        $weight = self::weightSummary($measurements);
        $bodyFat = self::bodyFatSummary($measurements);
        $kcal = self::kcalSummary($user, $from, $to);
        $activeDaysPct = self::activeDaysPct($user, $from, $to);

        return [
            'weight'           => $weight,
            'body_fat_pct'     => $bodyFat,
            'kcal'             => $kcal,
            'active_days_pct'  => $activeDaysPct,
            'period'           => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
                'days' => (int) abs($from->diffInDays($to)) + 1,
            ],
        ];
    }

    /**
     * @param 'weight'|'body_fat_pct'|'waist_cm'|'hips_cm'|'chest_cm'|'biceps_cm'|'kcal'|'protein_g'|'fat_g'|'carbs_g'|'steps' $metric
     * @return array<string, mixed>
     */
    public static function series(User $user, string $metric, Carbon $from, Carbon $to): array
    {
        $points = self::pointsForMetric($user, $metric, $from, $to);
        $rolling = self::rollingAverage($points, 7);

        return [
            'metric'         => $metric,
            'points'         => $points,
            'rolling_avg_7d' => $rolling,
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Measurement> $measurements */
    private static function weightSummary($measurements): array
    {
        $withWeight = $measurements->filter(fn ($m) => $m->weight_kg !== null);
        if ($withWeight->isEmpty()) {
            return ['start' => null, 'end' => null, 'delta_kg' => null, 'trend_kg_per_week' => null];
        }
        // One per day: keep the last measurement of each day
        $byDay = $withWeight->groupBy(fn ($m) => $m->measured_at->toDateString());
        $dailyLast = $byDay->map(fn ($group) => $group->sortBy('measured_at')->last());
        $sorted = $dailyLast->sortKeys();

        $first = $sorted->first();
        $last  = $sorted->last();
        $start = (float) $first->weight_kg;
        $end   = (float) $last->weight_kg;

        // Linear regression slope on the whole range
        $slopePerDay = self::slopePerDay(
            $sorted->map(fn ($m) => [
                'ts' => $m->measured_at->timestamp,
                'v'  => (float) $m->weight_kg,
            ])->values()->toArray(),
        );
        $trend = $slopePerDay !== null ? round($slopePerDay * 7, 2) : null;

        return [
            'start'             => round($start, 1),
            'end'               => round($end, 1),
            'delta_kg'          => round($end - $start, 1),
            'trend_kg_per_week' => $trend,
        ];
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Measurement> $measurements */
    private static function bodyFatSummary($measurements): array
    {
        $withBf = $measurements->filter(fn ($m) => $m->body_fat_pct !== null);
        if ($withBf->isEmpty()) {
            return ['start' => null, 'end' => null, 'delta_pct' => null];
        }
        $first = $withBf->first();
        $last = $withBf->last();
        return [
            'start'     => round((float) $first->body_fat_pct, 1),
            'end'       => round((float) $last->body_fat_pct, 1),
            'delta_pct' => round((float) $last->body_fat_pct - (float) $first->body_fat_pct, 1),
        ];
    }

    private static function kcalSummary(User $user, Carbon $from, Carbon $to): array
    {
        $entries = DayEntry::where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('meals')
            ->get();

        $tracked = $entries->filter(fn ($e) => $e->meals->isNotEmpty());
        $daysTracked = $tracked->count();

        $kcalTotals = $tracked->map(fn ($e) => (float) $e->meals->sum('kcal'));
        $avg = $daysTracked > 0 ? (int) round($kcalTotals->avg()) : null;

        // vs goal — sum (totals - goal) / daysTracked
        $deficitSum = 0.0;
        $deficitCount = 0;
        foreach ($tracked as $entry) {
            $goal = GoalResolver::forDate($user, Carbon::parse($entry->date));
            if (!$goal) continue;
            $kcal = (float) $entry->meals->sum('kcal');
            $deficitSum += ($kcal - $goal->kcal);
            $deficitCount++;
        }
        $vsGoal = $deficitCount > 0 ? (int) round($deficitSum / $deficitCount) : null;

        return [
            'avg'           => $avg,
            'vs_goal'       => $vsGoal,
            'days_tracked'  => $daysTracked,
            'deficit_avg'   => $vsGoal,
        ];
    }

    private static function activeDaysPct(User $user, Carbon $from, Carbon $to): int
    {
        $totalDays = (int) abs($from->diffInDays($to)) + 1;
        if ($totalDays === 0) return 0;
        $activeDays = DayEntry::where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('meals')
            ->count();
        return (int) round(($activeDays / $totalDays) * 100);
    }

    /** @return list<array{date: string, value: float|null}> */
    private static function pointsForMetric(User $user, string $metric, Carbon $from, Carbon $to): array
    {
        $measurementFields = ['weight_kg', 'body_fat_pct', 'waist_cm', 'hips_cm', 'chest_cm', 'biceps_cm'];
        if (in_array(self::dbField($metric), $measurementFields, true)) {
            return self::measurementPoints($user, self::dbField($metric), $from, $to);
        }
        $mealFields = ['kcal', 'protein_g', 'fat_g', 'carbs_g'];
        if (in_array($metric, $mealFields, true)) {
            return self::dailyMealAggregate($user, $metric, $from, $to);
        }
        if ($metric === 'steps') {
            return self::stepsPoints($user, $from, $to);
        }
        return [];
    }

    private static function dbField(string $metric): string
    {
        return match ($metric) {
            'weight'       => 'weight_kg',
            'body_fat_pct' => 'body_fat_pct',
            'waist_cm'     => 'waist_cm',
            'hips_cm'      => 'hips_cm',
            'chest_cm'     => 'chest_cm',
            'biceps_cm'    => 'biceps_cm',
            default        => $metric,
        };
    }

    /** @return list<array{date: string, value: float|null}> */
    private static function measurementPoints(User $user, string $field, Carbon $from, Carbon $to): array
    {
        $rows = Measurement::where('user_id', $user->id)
            ->whereBetween('measured_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->whereNotNull($field)
            ->orderBy('measured_at')
            ->get([$field, 'measured_at']);

        // One value per day (latest if multiple)
        $byDate = [];
        foreach ($rows as $r) {
            $d = $r->measured_at->toDateString();
            $byDate[$d] = (float) $r->$field;
        }
        $out = [];
        foreach ($byDate as $d => $v) $out[] = ['date' => $d, 'value' => $v];
        return $out;
    }

    /** @return list<array{date: string, value: float|null}> */
    private static function dailyMealAggregate(User $user, string $field, Carbon $from, Carbon $to): array
    {
        $entries = DayEntry::where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('meals')
            ->orderBy('date')
            ->get();
        $out = [];
        foreach ($entries as $entry) {
            $sum = (float) $entry->meals->sum($field);
            if ($entry->meals->isEmpty()) continue;
            $out[] = ['date' => $entry->date->toDateString(), 'value' => round($sum, 1)];
        }
        return $out;
    }

    /** @return list<array{date: string, value: float|null}> */
    private static function stepsPoints(User $user, Carbon $from, Carbon $to): array
    {
        $entries = DayEntry::where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('steps')
            ->orderBy('date')
            ->get(['date', 'steps']);
        $out = [];
        foreach ($entries as $entry) {
            $out[] = ['date' => $entry->date->toDateString(), 'value' => (float) $entry->steps];
        }
        return $out;
    }

    /**
     * @param list<array{date: string, value: float|null}> $points
     * @return list<array{date: string, value: float|null}>
     */
    private static function rollingAverage(array $points, int $window): array
    {
        $out = [];
        $values = array_map(fn ($p) => $p['value'], $points);
        for ($i = 0; $i < count($points); $i++) {
            $start = max(0, $i - $window + 1);
            $slice = array_slice($values, $start, $i - $start + 1);
            $slice = array_filter($slice, fn ($v) => $v !== null);
            $avg = count($slice) > 0 ? array_sum($slice) / count($slice) : null;
            $out[] = ['date' => $points[$i]['date'], 'value' => $avg !== null ? round($avg, 2) : null];
        }
        return $out;
    }

    /** @param list<array{ts: int, v: float}> $rows */
    private static function slopePerDay(array $rows): ?float
    {
        $n = count($rows);
        if ($n < 2) return null;
        $baseTs = $rows[0]['ts'];
        $sumX = 0; $sumY = 0; $sumXY = 0; $sumXX = 0;
        foreach ($rows as $r) {
            $x = ($r['ts'] - $baseTs) / 86400;
            $y = $r['v'];
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
        }
        $denom = $n * $sumXX - $sumX * $sumX;
        if (abs($denom) < 1e-9) return null;
        return ($n * $sumXY - $sumX * $sumY) / $denom;
    }
}
