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
            'measured_at'       => ['required', 'date'],
            'weight_kg'         => ['required', 'numeric', 'min:30', 'max:300'],
            'body_fat_pct'      => ['nullable', 'numeric', 'min:1', 'max:70'],
            'muscle_mass_kg'    => ['nullable', 'numeric', 'min:10', 'max:150'],
            'body_water_pct'    => ['nullable', 'numeric', 'min:1', 'max:80'],
            'visceral_fat_level'=> ['nullable', 'integer', 'min:1', 'max:30'],
            'bone_mass_kg'      => ['nullable', 'numeric', 'min:0.5', 'max:10'],
            'protein_pct'       => ['nullable', 'numeric', 'min:1', 'max:50'],
            'heart_rate_bpm'    => ['nullable', 'integer', 'min:30', 'max:250'],
            'source'            => ['nullable', 'string', 'max:20'],
        ];
    }
}
