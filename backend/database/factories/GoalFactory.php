<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'kcal' => 1700,
            'protein_g' => 150,
            'fat_g' => 60,
            'carbs_g' => 140,
            'note' => null,
        ];
    }
}
