<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Tdee\TdeeCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $tdee = null;
        if ($this->weight_kg !== null) {
            $tdee = TdeeCalculator::compute($this->resource, $this->weight_kg)->total;
        }

        return [
            'gender'         => $this->gender,
            'birth_date'     => $this->birth_date?->toDateString(),
            'height_cm'      => $this->height_cm,
            'activity_level' => $this->activity_level,
            'tdee_kcal'      => $tdee,
        ];
    }
}
