<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'type',
        'kcal',
        'protein_g',
        'fat_g',
        'carbs_g',
        'note',
    ];

    protected static function booted(): void
    {
        static::creating(function (Goal $goal) {
            $goal->uuid = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'kcal' => 'integer',
            'protein_g' => 'integer',
            'fat_g' => 'integer',
            'carbs_g' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
