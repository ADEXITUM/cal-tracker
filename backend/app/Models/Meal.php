<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Meal extends Model
{
    /** @use HasFactory<MealFactory> */
    use HasFactory;

    protected $fillable = [
        'day_entry_id',
        'user_id',
        'dish_id',
        'slot',
        'eaten_at',
        'grams',
        'name',
        'kcal',
        'protein_g',
        'fat_g',
        'carbs_g',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Meal $m) => $m->uuid = (string) Str::uuid());

        // Update dish usage stats when a meal referencing a dish is created
        static::created(function (Meal $m) {
            if ($m->dish_id) {
                Dish::where('id', $m->dish_id)->update([
                    'usage_count' => \DB::raw('usage_count + 1'),
                    'last_used_at' => now(),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'eaten_at'  => 'datetime',
            'grams'     => 'float',
            'kcal'      => 'float',
            'protein_g' => 'float',
            'fat_g'     => 'float',
            'carbs_g'   => 'float',
        ];
    }

    public function dayEntry(): BelongsTo
    {
        return $this->belongsTo(DayEntry::class);
    }

    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
