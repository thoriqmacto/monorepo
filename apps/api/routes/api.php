<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\NoteController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json([
    'ok' => true,
    'name' => config('app.name'),
    'time' => now()->toIso8601String(),
]));

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateMe']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Example resource — safe to remove. See NoteController.
        Route::apiResource('notes', NoteController::class)
            ->only(['index', 'store', 'destroy']);
    });
});
