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
        'muscle_mass_kg',
        'body_water_pct',
        'visceral_fat_level',
        'bone_mass_kg',
        'protein_pct',
        'heart_rate_bpm',
        'source',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Measurement $m) => $m->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'measured_at'       => 'datetime',
            'weight_kg'         => 'float',
            'body_fat_pct'      => 'float',
            'muscle_mass_kg'    => 'float',
            'body_water_pct'    => 'float',
            'visceral_fat_level' => 'integer',
            'bone_mass_kg'      => 'float',
            'protein_pct'       => 'float',
            'heart_rate_bpm'    => 'integer',
        ];
    }

    public function dayEntry(): BelongsTo
    {
        return $this->belongsTo(DayEntry::class);
    }
}
