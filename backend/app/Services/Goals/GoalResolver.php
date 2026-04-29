<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;

class GoalResolver
{
    public static function forDate(User $user, Carbon $date): ?Goal
    {
        return Goal::where('user_id', $user->id)
            ->where('start_date', '<=', $date->toDateString())
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $date->toDateString());
            })
            ->orderByDesc('start_date')
            ->first();
    }

    public static function closeOpenGoal(User $user, Carbon $newStartDate): void
    {
        Goal::where('user_id', $user->id)
            ->whereNull('end_date')
            ->where('start_date', '<', $newStartDate->toDateString())
            ->update(['end_date' => $newStartDate->subDay()->toDateString()]);
    }
}
