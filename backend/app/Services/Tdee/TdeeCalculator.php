<?php

declare(strict_types=1);

namespace App\Services\Tdee;

use App\Models\Profile;
use Carbon\Carbon;

class TdeeCalculator
{
    /** Sedentary BMR multiplier — covers sleep + basic life without intentional walking/training. */
    public const BASE_MULTIPLIER = 1.2;

    /** kcal per step per kg of bodyweight (rough average for walking pace). */
    public const STEP_KCAL_PER_KG = 0.0005;

    /** @param array<array{kcal_burned: int|null}> $workouts */
    public static function compute(
        Profile $profile,
        float $weightKg,
        ?int $steps = null,
        array $workouts = [],
    ): TdeeBreakdown {
        $age = Carbon::parse($profile->birth_date)->age;

        $bmr = $profile->gender === 'male'
            ? (int) round(10 * $weightKg + 6.25 * $profile->height_cm - 5 * $age + 5)
            : (int) round(10 * $weightKg + 6.25 * $profile->height_cm - 5 * $age - 161);

        $baseKcal = (int) round($bmr * self::BASE_MULTIPLIER);

        $stepsKcal = 0;
        if ($steps !== null && $steps > 0) {
            $stepsKcal = (int) round($steps * $weightKg * self::STEP_KCAL_PER_KG);
        }

        $workoutsKcal = (int) array_sum(array_column($workouts, 'kcal_burned'));

        $total = $baseKcal + $stepsKcal + $workoutsKcal;

        return new TdeeBreakdown(
            bmr: $bmr,
            baseKcal: $baseKcal,
            stepsKcal: $stepsKcal,
            workoutsKcal: $workoutsKcal,
            total: $total,
        );
    }
}
