<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;

// Routes d'authentification admin (sans middleware)
Route::prefix('api/admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('admin.api.login');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.api.logout');
});

// Routes protégées admin (avec middleware)
Route::prefix('api/admin')->middleware(['auth:sanctum', 'admin.auth'])->group(function () {
    Route::get('/user', [AuthController::class, 'user'])->name('admin.api.user');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('admin.api.refresh');
    Route::get('/check', [AuthController::class, 'check'])->name('admin.api.check');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.api.dashboard');
});