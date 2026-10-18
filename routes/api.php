<?php

use App\Http\Controllers\Api\V1\UserProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'abilities:profile:read', 'throttle:api-v1'])->group(function (): void {
    Route::get('/user', UserProfileController::class)->name('user.show');
});

// Compatibility endpoint for existing Sanctum clients. New consumers must use /api/v1.
Route::get('/user', function (Request $request) {
    $user = $request->user();

    return response()->json([
        'id' => $user->getKey(),
        'name' => $user->name,
        'email' => $user->email,
    ]);
})->middleware(['auth:sanctum', 'throttle:api-v1'])->name('api.user.legacy');
