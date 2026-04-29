<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'      => $this->uuid,
            'slot'      => $this->slot,
            'eaten_at'  => $this->eaten_at?->toIso8601String(),
            'dish_uuid' => $this->dish?->uuid,
            'grams'     => $this->grams,
            'name'      => $this->name,
            'kcal'      => (float) $this->kcal,
            'protein_g' => (float) $this->protein_g,
            'fat_g'     => (float) $this->fat_g,
            'carbs_g'   => (float) $this->carbs_g,
        ];
    }
}
