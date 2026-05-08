<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DishFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Dish extends Model
{
    /** @use HasFactory<DishFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'kcal_per_100g',
        'protein_per_100g',
        'fat_per_100g',
        'carbs_per_100g',
        'is_piece',
        'piece_grams',
        'piece_label',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Dish $d) => $d->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return [
            'kcal_per_100g'    => 'float',
            'protein_per_100g' => 'float',
            'fat_per_100g'     => 'float',
            'carbs_per_100g'   => 'float',
            'usage_count'      => 'integer',
            'last_used_at'     => 'datetime',
            'archived_at'      => 'datetime',
            'is_piece'         => 'boolean',
            'piece_grams'      => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
