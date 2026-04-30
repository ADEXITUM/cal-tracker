<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'name'         => $this->name,
            'duration_min' => $this->duration_min,
            'kcal_burned'  => $this->kcal_burned,
        ];
    }
}
