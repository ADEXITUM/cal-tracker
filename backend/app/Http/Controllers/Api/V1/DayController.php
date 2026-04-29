<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DayEntryRequest;
use App\Http\Resources\MeasurementResource;
use App\Models\DayEntry;
use App\Services\Days\DayAggregator;
use App\Services\Goals\GoalResolver;
use App\Services\Modes\ModeClassifier;
use App\Services\Tdee\TdeeCalculator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function show(Request $request, string $date): JsonResponse
    {
        $user = $request->user()->load('profile');
        $carbon = Carbon::parse($date, $user->timezone);
        $data = DayAggregator::forDate($user, $carbon);

        return response()->json(['data' => $this->serialize($data)]);
    }

    public function update(DayEntryRequest $request, string $date): JsonResponse
    {
        $user = $request->user();
        $entry = DayEntry::firstOrCreate(
            ['user_id' => $user->id, 'date' => $date],
        );
        $entry->update($request->validated());

        return response()->json(['data' => $entry]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $from = $request->query('from');
        $to   = $request->query('to');

        $query = DayEntry::where('user_id', $user->id)
            ->with(['meals', 'measurements'])
            ->orderBy('date');

        if ($from) $query->where('date', '>=', $from);
        if ($to)   $query->where('date', '<=', $to);

        $entries = $query->get();

        $result = $entries->map(function (DayEntry $entry) use ($user) {
            $carbon = Carbon::parse($entry->date, $user->timezone);
            $goal = GoalResolver::forDate($user, $carbon);

            $totals = [
                'kcal'      => round((float) $entry->meals->sum('kcal'), 1),
                'protein_g' => round((float) $entry->meals->sum('protein_g'), 1),
                'fat_g'     => round((float) $entry->meals->sum('fat_g'), 1),
                'carbs_g'   => round((float) $entry->meals->sum('carbs_g'), 1),
            ];

            $weightKg = $entry->measurements->sortByDesc('measured_at')->first()?->weight_kg;
            $modeCode = null;
            if ($goal && $user->profile && $weightKg) {
                $tdee = TdeeCalculator::compute($user->profile, (float) $weightKg);
                $modeCode = ModeClassifier::classify($goal->kcal, $tdee->total)->code;
            }

            return [
                'date'           => $entry->date->toDateString(),
                'totals'         => $totals,
                'weight_kg'      => $weightKg ? (float) $weightKg : null,
                'mode_code'      => $modeCode,
                'delta_from_goal' => $goal ? (int) ($totals['kcal'] - $goal->kcal) : null,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /** @param array<string, mixed> $data */
    private function serialize(array $data): array
    {
        return [
            'date'      => $data['date'],
            'day_entry' => $data['day_entry'] ? [
                'mood'        => $data['day_entry']->mood,
                'wellbeing'   => $data['day_entry']->wellbeing,
                'sleep_hours' => $data['day_entry']->sleep_hours,
                'steps'       => $data['day_entry']->steps,
                'notes'       => $data['day_entry']->notes,
            ] : null,
            'goal'         => $data['goal'] ? [
                'kcal'      => $data['goal']->kcal,
                'protein_g' => $data['goal']->protein_g,
                'fat_g'     => $data['goal']->fat_g,
                'carbs_g'   => $data['goal']->carbs_g,
            ] : null,
            'tdee'         => $data['tdee'],
            'mode'         => $data['mode'],
            'totals'       => $data['totals'],
            'meals'        => $data['meals']->map(fn ($m) => [
                'uuid'      => $m->uuid,
                'slot'      => $m->slot,
                'eaten_at'  => $m->eaten_at?->toIso8601String(),
                'name'      => $m->name,
                'grams'     => $m->grams,
                'kcal'      => (float) $m->kcal,
                'protein_g' => (float) $m->protein_g,
                'fat_g'     => (float) $m->fat_g,
                'carbs_g'   => (float) $m->carbs_g,
            ]),
            'measurements' => $data['measurements']->map(fn ($m) => [
                'uuid'        => $m->uuid,
                'measured_at' => $m->measured_at?->toIso8601String(),
                'weight_kg'   => (float) $m->weight_kg,
                'body_fat_pct' => $m->body_fat_pct ? (float) $m->body_fat_pct : null,
            ]),
            'workouts'  => $data['workouts']->map(fn ($w) => [
                'uuid'         => $w->uuid,
                'name'         => $w->name,
                'duration_min' => $w->duration_min,
                'kcal_burned'  => $w->kcal_burned,
            ]),
            'insights' => [],
        ];
    }
}
