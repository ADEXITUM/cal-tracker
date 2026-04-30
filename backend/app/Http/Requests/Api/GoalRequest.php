<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class GoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'type'       => ['required', 'in:cut,maintenance,bulk'],
            'kcal'       => ['required', 'integer', 'min:800', 'max:6000'],
            'protein_g'  => ['required', 'integer', 'min:0', 'max:500'],
            'fat_g'      => ['required', 'integer', 'min:0', 'max:400'],
            'carbs_g'    => ['required', 'integer', 'min:0', 'max:1000'],
            'note'       => ['nullable', 'string', 'max:120'],
        ];
    }
}
