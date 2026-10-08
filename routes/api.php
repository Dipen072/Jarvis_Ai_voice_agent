<?php

use App\Http\Controllers\Api\VoiceCommandController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ── Public ────────────────────────────────────────────────────────────────────

// Health check - no auth required
Route::get('/jarvis/health', [VoiceCommandController::class, 'health']);

// Auth routes
Route::post('/auth/register', [\App\Http\Controllers\Api\AuthController::class, 'register']);
Route::post('/auth/login',    [\App\Http\Controllers\Api\AuthController::class, 'login']);

// ── Authenticated ─────────────────────────────────────────────────────────────

Route::middleware(['auth:sanctum'])->group(function () {

    // Auth
    Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/auth/user',    fn(Request $r) => response()->json(['user' => $r->user()]));

    // Jarvis core
    Route::prefix('jarvis')->group(function () {
        // Rate-limit to 60 commands per minute per user
        Route::middleware('throttle:60,1')->group(function () {
            Route::post('/command',  [VoiceCommandController::class, 'command']);
        });

        Route::get('/history',     [VoiceCommandController::class, 'history']);
        Route::delete('/history',  [VoiceCommandController::class, 'clearHistory']);
        Route::get('/settings',    [VoiceCommandController::class, 'settings']);
        Route::put('/settings',    [VoiceCommandController::class, 'updateSettings']);
    });
});
