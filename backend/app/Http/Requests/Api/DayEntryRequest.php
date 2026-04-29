<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DayEntryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mood'        => ['nullable', 'integer', 'min:1', 'max:5'],
            'wellbeing'   => ['nullable', 'integer', 'min:1', 'max:5'],
            'sleep_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'steps'       => ['nullable', 'integer', 'min:0', 'max:200000'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ];
    }
}
