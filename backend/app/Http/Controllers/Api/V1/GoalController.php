<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->goals()->orderByDesc('start_date');

        if ($request->filled('from')) {
            $query->where('start_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('start_date', '<=', $request->to);
        }

        return response()->json([
            'data' => GoalResource::collection($query->get()),
        ]);
    }

    public function store(GoalRequest $request): JsonResponse
    {
        $user = $request->user();
        self::ensureNoOverlap($user, $request->start_date, $request->end_date, null);

        $goal = Goal::create([
            'user_id'    => $user->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new GoalResource($goal)], 201);
    }

    public function update(GoalRequest $request, string $uuid): JsonResponse
    {
        $goal = $request->user()->goals()->where('uuid', $uuid)->firstOrFail();
        self::ensureNoOverlap($request->user(), $request->start_date, $request->end_date, $goal->id);
        $goal->update($request->validated());

        return response()->json(['data' => new GoalResource($goal)]);
    }

    /**
     * Reject the request if the new/edited range overlaps any other goal.
     * Each calendar day must belong to at most one goal — the user must
     * close conflicting goals before adding a new one in that range.
     */
    private static function ensureNoOverlap(User $user, string $start, ?string $end, ?int $excludeId): void
    {
        $query = Goal::where('user_id', $user->id);
        if ($excludeId !== null) $query->where('id', '!=', $excludeId);

        // Existing goal [a..b] overlaps new [start..end] iff NOT (b < start OR a > end).
        // Open-ended sides treat as ±∞, so they never satisfy the strict-less/greater test.
        $query->where(function ($q) use ($start, $end) {
            $q->where(function ($q1) use ($start) {
                // existing.end >= start, where null end = ∞
                $q1->whereNull('end_date')->orWhere('end_date', '>=', $start);
            });
            if ($end !== null) {
                $q->where('start_date', '<=', $end);
            }
        });

        $overlapping = $query->orderBy('start_date')->get();
        if ($overlapping->isEmpty()) return;

        $list = $overlapping->map(fn ($g) => sprintf(
            '%s ккал, %s — %s',
            $g->kcal,
            Carbon::parse($g->start_date)->format('d.m.Y'),
            $g->end_date ? Carbon::parse($g->end_date)->format('d.m.Y') : 'без срока',
        ))->implode('; ');

        throw ValidationException::withMessages([
            'start_date' => "Цели не должны перекрываться. Сначала завершите: {$list}",
        ]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $goal = $request->user()->goals()->where('uuid', $uuid)->firstOrFail();
        $goal->delete();

        return response()->json(null, 204);
    }
}
