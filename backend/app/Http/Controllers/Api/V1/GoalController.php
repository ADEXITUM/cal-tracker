<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use App\Services\Goals\GoalResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $startDate = Carbon::parse($request->start_date);

        if (! $request->filled('end_date')) {
            GoalResolver::closeOpenGoal($user, $startDate);
        }

        $goal = Goal::create([
            'user_id'    => $user->id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new GoalResource($goal)], 201);
    }

    public function update(GoalRequest $request, string $uuid): JsonResponse
    {
        $goal = $request->user()->goals()->where('uuid', $uuid)->firstOrFail();
        $goal->update($request->validated());

        return response()->json(['data' => new GoalResource($goal)]);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $goal = $request->user()->goals()->where('uuid', $uuid)->firstOrFail();
        $goal->delete();

        return response()->json(null, 204);
    }
}
