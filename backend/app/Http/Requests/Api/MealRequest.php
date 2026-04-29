<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MealRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'slot'      => ['required', 'in:breakfast,lunch,snack,dinner,other'],
            'eaten_at'  => ['required', 'date'],
            // Dish-based
            'dish_uuid' => ['nullable', 'string'],
            'grams'     => ['nullable', 'numeric', 'min:1', 'max:5000'],
            // Ad-hoc
            'name'      => ['nullable', 'string', 'max:120'],
            'kcal'      => ['nullable', 'numeric', 'min:0'],
            'protein_g' => ['nullable', 'numeric', 'min:0'],
            'fat_g'     => ['nullable', 'numeric', 'min:0'],
            'carbs_g'   => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
