<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BathController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\FeedingController;
use App\Http\Controllers\ParasiteTreatmentController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetInvitationController;
use App\Http\Controllers\PetOwnerController;
use App\Http\Controllers\VaccinationController;
use App\Http\Controllers\VetVisitController;
use App\Http\Controllers\WalkController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

// Versioned API: every route lives under /api/v1 so older app builds keep working
// when a future /api/v2 changes the contract.
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('auth/me', [AuthController::class, 'update']);
        Route::delete('auth/me', [AuthController::class, 'destroy']);
        Route::post('auth/refresh', [AuthController::class, 'refresh']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);

        // Push notifications: this phone's Expo token.
        Route::post('devices', [DeviceTokenController::class, 'store']);
        Route::delete('devices', [DeviceTokenController::class, 'destroy']);

        Route::apiResource('pets', PetController::class);

        // Sharing a dog: one-time invitation codes, and leaving / removing a co-owner.
        Route::get('pets/{pet}/invitations', [PetInvitationController::class, 'index']);
        Route::post('pets/{pet}/invitations', [PetInvitationController::class, 'store']);
        Route::delete('pets/{pet}/invitations/{invitation}', [PetInvitationController::class, 'destroy']);
        Route::post('invitations/accept', [PetInvitationController::class, 'accept'])->middleware('throttle:invite-accept');
        Route::delete('pets/{pet}/users/{user}', [PetOwnerController::class, 'destroy']);

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
});
