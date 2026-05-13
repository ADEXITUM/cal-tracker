<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatMessage extends Model
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'sender_user_id',
        'role',
        'content',
    ];

    protected static function booted(): void
    {
        static::creating(fn (ChatMessage $m) => $m->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
