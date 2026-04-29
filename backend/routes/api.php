<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DayController;
use App\Http\Controllers\Api\V1\DishController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\MealController;
use App\Http\Controllers\Api\V1\MeasurementController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\WorkoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        $authThrottle = app()->isLocal() ? 'throttle:1000,1' : 'throttle:5,60';
        $loginThrottle = app()->isLocal() ? 'throttle:1000,1' : 'throttle:10,1';
        Route::post('register', [AuthController::class, 'register'])->middleware($authThrottle);
        Route::post('login', [AuthController::class, 'login'])->middleware($loginThrottle);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    $apiThrottle = app()->isLocal() ? 'throttle:10000,1' : 'throttle:120,1';
    Route::middleware(['auth:sanctum', $apiThrottle])->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'upsert']);

        Route::get('goals', [GoalController::class, 'index']);
        Route::post('goals', [GoalController::class, 'store']);
        Route::put('goals/{uuid}', [GoalController::class, 'update']);
        Route::delete('goals/{uuid}', [GoalController::class, 'destroy']);

        // Days
        Route::get('days', [DayController::class, 'index']);
        Route::get('days/{date}', [DayController::class, 'show']);
        Route::put('days/{date}', [DayController::class, 'update']);

        // Nested under date
        Route::post('days/{date}/meals', [MealController::class, 'store']);
        Route::post('days/{date}/measurements', [MeasurementController::class, 'store']);
        Route::post('days/{date}/workouts', [WorkoutController::class, 'store']);

        // By uuid
        Route::put('meals/{uuid}', [MealController::class, 'update']);
        Route::delete('meals/{uuid}', [MealController::class, 'destroy']);
        Route::put('measurements/{uuid}', [MeasurementController::class, 'update']);
        Route::delete('measurements/{uuid}', [MeasurementController::class, 'destroy']);
        Route::put('workouts/{uuid}', [WorkoutController::class, 'update']);
        Route::delete('workouts/{uuid}', [WorkoutController::class, 'destroy']);

        // Dishes
        Route::get('dishes', [DishController::class, 'index']);
        Route::post('dishes', [DishController::class, 'store']);
        Route::put('dishes/{uuid}', [DishController::class, 'update']);
        Route::delete('dishes/{uuid}', [DishController::class, 'destroy']);
    });
});
