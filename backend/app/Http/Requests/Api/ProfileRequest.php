<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'gender'         => ['required', 'in:male,female'],
            'birth_date'     => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'height_cm'      => ['required', 'integer', 'min:100', 'max:250'],
            'activity_level' => ['required', 'in:sedentary,light,moderate,active'],
        ];
    }
}
