<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkoutFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Workout extends Model
{
    /** @use HasFactory<WorkoutFactory> */
    use HasFactory;

    protected $fillable = [
        'day_entry_id',
        'user_id',
        'name',
        'duration_min',
        'kcal_burned',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Workout $w) => $w->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'kcal_burned'  => 'integer',
        ];
    }

    public function dayEntry(): BelongsTo
    {
        return $this->belongsTo(DayEntry::class);
    }
}
