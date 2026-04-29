<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'         => $this->uuid,
            'name'         => $this->name,
            'email'        => $this->email,
            'avatar_color' => $this->avatar_color,
            'timezone'     => $this->timezone,
            'has_profile'  => $this->profile !== null,
        ];
    }
}
