<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Dish;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Dish> */
class DishFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'name'             => fake()->words(2, true),
            'kcal_per_100g'    => fake()->randomFloat(1, 50, 500),
            'protein_per_100g' => fake()->randomFloat(1, 0, 40),
            'fat_per_100g'     => fake()->randomFloat(1, 0, 40),
            'carbs_per_100g'   => fake()->randomFloat(1, 0, 80),
        ];
    }
}
