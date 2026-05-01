<?php

use App\Http\Controllers\Api\Admin\CoiffeuseController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/documentation', [DocumentationController::class, 'ui']);
Route::get('/openapi.json', [DocumentationController::class, 'openApi']);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth.token')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

Route::middleware(['auth.token', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::apiResource('coiffeuses', CoiffeuseController::class)
            ->parameters(['coiffeuses' => 'coiffeuse']);
    });
