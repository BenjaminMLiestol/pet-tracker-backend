<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BathController;
use App\Http\Controllers\FeedingController;
use App\Http\Controllers\ParasiteTreatmentController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VetVisitController;
use App\Http\Controllers\WalkController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('throttle:auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/me', [AuthController::class, 'update']);
    Route::delete('auth/me', [AuthController::class, 'destroy']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('pets', PetController::class);

    // Activity resources
    Route::apiResource('feedings', FeedingController::class);
    Route::apiResource('walks', WalkController::class);
    Route::apiResource('baths', BathController::class);
    Route::apiResource('weights', WeightController::class);

    // Care resources
    Route::apiResource('vet-visits', VetVisitController::class);
    Route::apiResource('vaccinations', VaccinationController::class);
    Route::apiResource('parasite-treatments', ParasiteTreatmentController::class);
});
