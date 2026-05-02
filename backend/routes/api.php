<?php

use App\Http\Controllers\Api\Admin\CategorieCoiffureController;
use App\Http\Controllers\Api\Admin\CodePromoController;
use App\Http\Controllers\Api\Admin\CoiffeuseController;
use App\Http\Controllers\Api\Admin\CoiffureController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CategorieDepenseController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DepenseController;
use App\Http\Controllers\Api\Admin\ImageCoiffureController;
use App\Http\Controllers\Api\Admin\ListeNoireClientController;
use App\Http\Controllers\Api\Admin\OptionCoiffureController;
use App\Http\Controllers\Api\Admin\ParametreSystemeController;
use App\Http\Controllers\Api\Admin\PreferenceClientController;
use App\Http\Controllers\Api\Admin\RegleFideliteController;
use App\Http\Controllers\Api\Admin\VarianteCoiffureController;
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
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::apiResource('categories-coiffures', CategorieCoiffureController::class)
            ->parameters(['categories-coiffures' => 'categorieCoiffure']);
        Route::apiResource('coiffures', CoiffureController::class);
        Route::apiResource('variantes-coiffures', VarianteCoiffureController::class)
            ->parameters(['variantes-coiffures' => 'varianteCoiffure']);
        Route::apiResource('options-coiffures', OptionCoiffureController::class)
            ->parameters(['options-coiffures' => 'optionCoiffure']);
        Route::apiResource('images-coiffures', ImageCoiffureController::class)
            ->parameters(['images-coiffures' => 'imageCoiffure']);
        Route::apiResource('coiffeuses', CoiffeuseController::class)
            ->parameters(['coiffeuses' => 'coiffeuse']);
        Route::apiResource('parametres-systeme', ParametreSystemeController::class)
            ->parameters(['parametres-systeme' => 'parametreSysteme']);
        Route::apiResource('regles-fidelite', RegleFideliteController::class)
            ->parameters(['regles-fidelite' => 'regleFidelite']);
        Route::apiResource('codes-promo', CodePromoController::class)
            ->parameters(['codes-promo' => 'codePromo']);
        Route::apiResource('categories-depenses', CategorieDepenseController::class)
            ->parameters(['categories-depenses' => 'categorieDepense']);
        Route::apiResource('depenses', DepenseController::class);
        Route::patch('clients/{client}/blacklist', [ClientController::class, 'blacklist'])->name('clients.blacklist');
        Route::patch('clients/{client}/unblacklist', [ClientController::class, 'unblacklist'])->name('clients.unblacklist');
        Route::put('clients/{client}/preferences', [PreferenceClientController::class, 'updateForClient'])->name('clients.preferences.update');
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('preferences-clients', PreferenceClientController::class)->only(['index', 'show', 'update'])
            ->parameters(['preferences-clients' => 'preferenceClient']);
        Route::apiResource('liste-noire-clients', ListeNoireClientController::class)->only(['index', 'show', 'update'])
            ->parameters(['liste-noire-clients' => 'listeNoireClient']);
    });
