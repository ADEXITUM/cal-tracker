<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'               => $this->uuid,
            'measured_at'        => $this->measured_at?->toIso8601String(),
            'weight_kg'          => (float) $this->weight_kg,
            'body_fat_pct'       => $this->body_fat_pct ? (float) $this->body_fat_pct : null,
            'muscle_mass_kg'     => $this->muscle_mass_kg ? (float) $this->muscle_mass_kg : null,
            'body_water_pct'     => $this->body_water_pct ? (float) $this->body_water_pct : null,
            'visceral_fat_level' => $this->visceral_fat_level,
            'bone_mass_kg'       => $this->bone_mass_kg ? (float) $this->bone_mass_kg : null,
            'protein_pct'        => $this->protein_pct ? (float) $this->protein_pct : null,
            'heart_rate_bpm'     => $this->heart_rate_bpm,
            'source'             => $this->source,
        ];
    }
}
