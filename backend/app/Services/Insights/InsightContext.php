<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\DayEntry;
use App\Models\Goal;
use App\Models\User;
use App\Services\Modes\Mode;
use App\Services\Tdee\TdeeBreakdown;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class InsightContext
{
    /**
     * @param array{kcal: float, protein_g: float, fat_g: float, carbs_g: float} $totals
     */
    public function __construct(
        public User $user,
        public Carbon $date,
        public ?DayEntry $dayEntry,
        public ?Goal $goal,
        public ?TdeeBreakdown $tdee,
        public ?Mode $mode,
        public array $totals,
        public Collection $meals,
        public Collection $measurements,
        public Collection $workouts,
        public int $hoursIntoDay,
    ) {}

    public function isToday(): bool
    {
        return $this->date->isSameDay(Carbon::now($this->user->timezone));
    }

    public function isPast(): bool
    {
        return $this->date->isBefore(Carbon::today($this->user->timezone));
    }
}
