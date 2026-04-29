<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (! $profile) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        return response()->json(['data' => new ProfileResource($profile)]);
    }

    public function upsert(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->validated(),
        );

        $created = ! $profile->wasRecentlyCreated && $profile->wasChanged() === false
            ? false
            : $profile->wasRecentlyCreated;

        return response()->json(
            ['data' => new ProfileResource($profile)],
            $created ? 201 : 200,
        );
    }
}
