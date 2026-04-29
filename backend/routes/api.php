<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () { // full path: /api/v1/...

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:5,60');  // 5/hour per IP
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');  // 10/min per IP

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'upsert']);

        Route::get('goals', [GoalController::class, 'index']);
        Route::post('goals', [GoalController::class, 'store']);
        Route::put('goals/{uuid}', [GoalController::class, 'update']);
        Route::delete('goals/{uuid}', [GoalController::class, 'destroy']);
    });
});
