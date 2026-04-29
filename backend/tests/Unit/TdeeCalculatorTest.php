<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Tdee\TdeeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TdeeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfile(string $gender, string $birthDate, int $heightCm, string $activityLevel): \App\Models\Profile
    {
        return \App\Models\Profile::factory()->make([
            'gender'         => $gender,
            'birth_date'     => Carbon::parse($birthDate),
            'height_cm'      => $heightCm,
            'activity_level' => $activityLevel,
        ]);
    }

    public function test_bmr_male(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180, 'sedentary');
        $result = TdeeCalculator::compute($profile, 80.0);

        // BMR = 10*80 + 6.25*180 - 5*(age) + 5
        $age = Carbon::parse('1992-01-01')->age;
        $expected = (int) round(10 * 80 + 6.25 * 180 - 5 * $age + 5);
        $this->assertSame($expected, $result->bmr);
    }

    public function test_bmr_female(): void
    {
        $profile = $this->makeProfile('female', '1992-01-01', 165, 'sedentary');
        $result = TdeeCalculator::compute($profile, 60.0);

        $age = Carbon::parse('1992-01-01')->age;
        $expected = (int) round(10 * 60 + 6.25 * 165 - 5 * $age - 161);
        $this->assertSame($expected, $result->bmr);
    }

    public function test_sedentary_no_steps_no_workouts(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180, 'sedentary');
        $result = TdeeCalculator::compute($profile, 80.0);

        $this->assertSame(0, $result->stepsKcal);
        $this->assertSame(0, $result->workoutsKcal);
        $this->assertSame($result->bmr + $result->activityKcal, $result->total);
    }

    public function test_steps_add_to_total_for_sedentary(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180, 'sedentary');
        $result = TdeeCalculator::compute($profile, 80.0, steps: 10000);

        // stepsKcal = 10000 * 80 * 0.0005 * 1.0 = 400
        $this->assertSame(400, $result->stepsKcal);
    }

    public function test_active_user_steps_coefficient_reduces_bonus(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180, 'active');
        $result = TdeeCalculator::compute($profile, 80.0, steps: 10000);

        // stepsKcal = 10000 * 80 * 0.0005 * 0.2 = 80
        $this->assertSame(80, $result->stepsKcal);
    }

    public function test_workouts_add_to_total(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180, 'sedentary');
        $result = TdeeCalculator::compute($profile, 80.0, workouts: [
            ['kcal_burned' => 300],
            ['kcal_burned' => 150],
        ]);

        $this->assertSame(450, $result->workoutsKcal);
    }
}
