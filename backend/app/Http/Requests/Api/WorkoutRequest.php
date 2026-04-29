<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class WorkoutRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:120'],
            'duration_min' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'kcal_burned'  => ['nullable', 'integer', 'min:0', 'max:5000'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ];
    }
}
