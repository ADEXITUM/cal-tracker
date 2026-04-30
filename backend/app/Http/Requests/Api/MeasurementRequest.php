<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MeasurementRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'measured_at'  => ['required', 'date'],
            'weight_kg'    => ['required', 'numeric', 'min:30', 'max:300'],
            'body_fat_pct' => ['nullable', 'numeric', 'min:1', 'max:70'],
            'waist_cm'     => ['nullable', 'numeric', 'min:30', 'max:200'],
            'hips_cm'      => ['nullable', 'numeric', 'min:30', 'max:200'],
            'chest_cm'     => ['nullable', 'numeric', 'min:30', 'max:200'],
            'biceps_cm'    => ['nullable', 'numeric', 'min:10', 'max:80'],
        ];
    }
}
