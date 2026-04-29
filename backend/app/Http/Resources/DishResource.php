<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DishResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'             => $this->uuid,
            'name'             => $this->name,
            'kcal_per_100g'    => (float) $this->kcal_per_100g,
            'protein_per_100g' => (float) $this->protein_per_100g,
            'fat_per_100g'     => (float) $this->fat_per_100g,
            'carbs_per_100g'   => (float) $this->carbs_per_100g,
            'usage_count'      => $this->usage_count,
            'last_used_at'     => $this->last_used_at?->toIso8601String(),
        ];
    }
}
