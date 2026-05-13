<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\UpdateMeRequest;
use App\Http\Resources\GoalResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Goals\GoalResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'data' => [
                'user'  => new UserResource($user->load('profile')),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');
        $currentGoal = GoalResolver::forDate($user, Carbon::today($user->timezone));

        return response()->json([
            'data' => [
                'user'         => new UserResource($user),
                'current_goal' => $currentGoal ? new GoalResource($currentGoal) : null,
            ],
        ]);
    }

    public function updateMe(UpdateMeRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('profile')),
            ],
        ]);
    }
}
