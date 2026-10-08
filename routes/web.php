<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JarvisDashboardController;
use App\Http\Controllers\AuthWebController;

// Redirect root to Jarvis dashboard
Route::get('/', fn() => redirect('/jarvis'));

// ── Auth Web Routes ────────────────────────────────────────────────────────────
Route::get('/login',    [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login',   [AuthWebController::class, 'login']);
Route::get('/register', [AuthWebController::class, 'showRegister'])->name('register');
Route::post('/register',[AuthWebController::class, 'register']);
Route::post('/logout',  [AuthWebController::class, 'logout'])->name('logout');

// ── Jarvis Dashboard ───────────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/jarvis', [JarvisDashboardController::class, 'index'])->name('jarvis.dashboard');
});
