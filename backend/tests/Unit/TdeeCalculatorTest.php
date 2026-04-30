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

    private function makeProfile(string $gender, string $birthDate, int $heightCm): \App\Models\Profile
    {
        return \App\Models\Profile::factory()->make([
            'gender'     => $gender,
            'birth_date' => Carbon::parse($birthDate),
            'height_cm'  => $heightCm,
        ]);
    }

    public function test_bmr_male(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0);

        $age = Carbon::parse('1992-01-01')->age;
        $expected = (int) round(10 * 80 + 6.25 * 180 - 5 * $age + 5);
        $this->assertSame($expected, $result->bmr);
    }

    public function test_bmr_female(): void
    {
        $profile = $this->makeProfile('female', '1992-01-01', 165);
        $result = TdeeCalculator::compute($profile, 60.0);

        $age = Carbon::parse('1992-01-01')->age;
        $expected = (int) round(10 * 60 + 6.25 * 165 - 5 * $age - 161);
        $this->assertSame($expected, $result->bmr);
    }

    public function test_base_kcal_uses_sedentary_multiplier(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0);

        $this->assertSame((int) round($result->bmr * 1.2), $result->baseKcal);
    }

    public function test_no_steps_no_workouts(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0);

        $this->assertSame(0, $result->stepsKcal);
        $this->assertSame(0, $result->workoutsKcal);
        $this->assertSame($result->baseKcal, $result->total);
    }

    public function test_steps_add_to_total(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0, steps: 10000);

        // stepsKcal = 10000 * 80 * 0.0005 = 400
        $this->assertSame(400, $result->stepsKcal);
    }

    public function test_workouts_add_to_total(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0, workouts: [
            ['kcal_burned' => 300],
            ['kcal_burned' => 150],
        ]);

        $this->assertSame(450, $result->workoutsKcal);
    }

    public function test_total_sums_components(): void
    {
        $profile = $this->makeProfile('male', '1992-01-01', 180);
        $result = TdeeCalculator::compute($profile, 80.0, steps: 8000, workouts: [
            ['kcal_burned' => 200],
        ]);

        $this->assertSame(
            $result->baseKcal + $result->stepsKcal + $result->workoutsKcal,
            $result->total,
        );
    }
}
