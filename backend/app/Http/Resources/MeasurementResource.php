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
            'uuid'         => $this->uuid,
            'measured_at'  => $this->measured_at?->toIso8601String(),
            'weight_kg'    => (float) $this->weight_kg,
            'body_fat_pct' => $this->body_fat_pct !== null ? (float) $this->body_fat_pct : null,
            'waist_cm'     => $this->waist_cm !== null ? (float) $this->waist_cm : null,
            'hips_cm'      => $this->hips_cm !== null ? (float) $this->hips_cm : null,
            'chest_cm'     => $this->chest_cm !== null ? (float) $this->chest_cm : null,
            'biceps_cm'    => $this->biceps_cm !== null ? (float) $this->biceps_cm : null,
        ];
    }
}
