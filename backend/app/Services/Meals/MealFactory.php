<?php

declare(strict_types=1);

namespace App\Services\Meals;

use App\Models\DayEntry;
use App\Models\Dish;
use App\Models\Meal;
use App\Models\User;
use App\Support\Numbers;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class MealFactory
{
    public static function fromDish(DayEntry $entry, User $user, Dish $dish, float $grams, string $slot, Carbon $eatenAt): Meal
    {
        // Dish nutrition is stored per 100g — scale by actual portion.
        $factor = $grams / Numbers::NUTRITION_REFERENCE_GRAMS;

        return Meal::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $user->id,
            'dish_id'      => $dish->id,
            'slot'         => $slot,
            'eaten_at'     => $eatenAt,
            'grams'        => $grams,
            'name'         => $dish->name,
            'kcal'         => round($dish->kcal_per_100g * $factor, 2),
            'protein_g'    => round($dish->protein_per_100g * $factor, 2),
            'fat_g'        => round($dish->fat_per_100g * $factor, 2),
            'carbs_g'      => round($dish->carbs_per_100g * $factor, 2),
        ]);
    }

    public static function fromAdHoc(DayEntry $entry, User $user, string $name, string $slot, Carbon $eatenAt, float $kcal, float $proteinG, float $fatG, float $carbsG): Meal
    {
        return Meal::create([
            'day_entry_id' => $entry->id,
            'user_id'      => $user->id,
            'dish_id'      => null,
            'slot'         => $slot,
            'eaten_at'     => $eatenAt,
            'grams'        => null,
            'name'         => $name,
            'kcal'         => $kcal,
            'protein_g'    => $proteinG,
            'fat_g'        => $fatG,
            'carbs_g'      => $carbsG,
        ]);
    }

    public static function getOrCreateEntry(User $user, string $date): DayEntry
    {
        return DayEntry::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
        );
    }
}
