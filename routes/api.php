<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BathController;
use App\Http\Controllers\FeedingController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalkController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Users & Pets CRUD
    Route::apiResource('users', UserController::class);
    Route::apiResource('pets', PetController::class);

    // Activity resources
    Route::apiResource('feedings', FeedingController::class);
    Route::apiResource('walks', WalkController::class);
    Route::apiResource('baths', BathController::class);
    Route::apiResource('weights', WeightController::class);
});
