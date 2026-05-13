<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid'       => $this->uuid,
            'role'       => $this->role,
            'content'    => $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
            'sender'     => $this->sender ? [
                'uuid'         => $this->sender->uuid,
                'name'         => $this->sender->name,
                'avatar_color' => $this->sender->avatar_color,
            ] : null,
        ];
    }
}
