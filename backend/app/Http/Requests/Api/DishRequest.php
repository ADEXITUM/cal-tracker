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
            'is_piece'         => ['sometimes', 'boolean'],
            // Required only when is_piece=true; the column itself is nullable so
            // grams-only dishes don't carry a meaningless "1 piece = 0 g".
            'piece_grams'      => ['required_if:is_piece,true', 'nullable', 'numeric', 'min:0.1', 'max:5000'],
            'piece_label'      => ['required_if:is_piece,true', 'nullable', 'string', 'max:24'],
        ];
    }
}
