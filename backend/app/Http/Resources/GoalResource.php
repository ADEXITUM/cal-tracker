<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'start_date' => $this->start_date?->toDateString(),
            'end_date'   => $this->end_date?->toDateString(),
            'kcal'       => $this->kcal,
            'protein_g'  => $this->protein_g,
            'fat_g'      => $this->fat_g,
            'carbs_g'    => $this->carbs_g,
            'note'       => $this->note,
        ];
    }
}
