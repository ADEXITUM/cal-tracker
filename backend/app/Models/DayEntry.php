<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DayEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DayEntry extends Model
{
    /** @use HasFactory<DayEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'mood',
        'wellbeing',
        'sleep_hours',
        'steps',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date'        => 'date',
            'mood'        => 'integer',
            'wellbeing'   => 'integer',
            'sleep_hours' => 'float',
            'steps'       => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }
}
