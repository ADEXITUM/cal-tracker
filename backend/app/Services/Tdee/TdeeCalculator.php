<?php

declare(strict_types=1);

namespace App\Services\Tdee;

use App\Models\Profile;
use Carbon\Carbon;

class TdeeCalculator
{
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

        $multipliers = [
            'sedentary' => 1.2,
            'light'     => 1.375,
            'moderate'  => 1.55,
            'active'    => 1.725,
        ];
        $multiplier = $multipliers[$profile->activity_level];
        $activityKcal = (int) round($bmr * ($multiplier - 1));

        $stepsCoefficients = [
            'sedentary' => 1.0,
            'light'     => 0.7,
            'moderate'  => 0.4,
            'active'    => 0.2,
        ];
        $stepsKcal = 0;
        if ($steps !== null && $steps > 0) {
            $coeff = $stepsCoefficients[$profile->activity_level];
            $stepsKcal = (int) round($steps * $weightKg * 0.0005 * $coeff);
        }

        $workoutsKcal = (int) array_sum(array_column($workouts, 'kcal_burned'));

        $total = $bmr + $activityKcal + $stepsKcal + $workoutsKcal;

        return new TdeeBreakdown(
            bmr: $bmr,
            activityKcal: $activityKcal,
            stepsKcal: $stepsKcal,
            workoutsKcal: $workoutsKcal,
            total: $total,
        );
    }
}
