<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Measurement extends Model
{
    /** @use HasFactory<MeasurementFactory> */
    use HasFactory;

    protected $fillable = [
        'day_entry_id',
        'user_id',
        'measured_at',
        'weight_kg',
        'body_fat_pct',
        'waist_cm',
        'hips_cm',
        'chest_cm',
        'biceps_cm',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Measurement $m) => $m->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'measured_at'  => 'datetime',
            'weight_kg'    => 'float',
            'body_fat_pct' => 'float',
            'waist_cm'     => 'float',
            'hips_cm'      => 'float',
            'chest_cm'     => 'float',
            'biceps_cm'    => 'float',
        ];
    }

    public function dayEntry(): BelongsTo
    {
        return $this->belongsTo(DayEntry::class);
    }
}
