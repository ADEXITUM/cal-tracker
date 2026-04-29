<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DishRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:120'],
            'kcal_per_100g'    => ['required', 'numeric', 'min:0', 'max:900'],
            'protein_per_100g' => ['required', 'numeric', 'min:0', 'max:100'],
            'fat_per_100g'     => ['required', 'numeric', 'min:0', 'max:100'],
            'carbs_per_100g'   => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
