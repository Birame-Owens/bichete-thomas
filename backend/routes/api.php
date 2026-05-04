<?php

use App\Http\Controllers\Api\Admin\CategorieCoiffureController;
use App\Http\Controllers\Api\Admin\CaisseController;
use App\Http\Controllers\Api\Admin\CodePromoController;
use App\Http\Controllers\Api\Admin\CoiffeuseController;
use App\Http\Controllers\Api\Admin\CoiffureController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CategorieDepenseController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DepenseController;
use App\Http\Controllers\Api\Admin\EvenementAnalyticsController;
use App\Http\Controllers\Api\Admin\GeranteController;
use App\Http\Controllers\Api\Admin\ImageCoiffureController;
use App\Http\Controllers\Api\Admin\ListeNoireClientController;
use App\Http\Controllers\Api\Admin\LogSystemeController;
use App\Http\Controllers\Api\Admin\MouvementCaisseController;
use App\Http\Controllers\Api\Admin\OptionCoiffureController;
use App\Http\Controllers\Api\Admin\PageSeoController;
use App\Http\Controllers\Api\Admin\PaiementController;
use App\Http\Controllers\Api\Admin\ParametreSystemeController;
use App\Http\Controllers\Api\Admin\PreferenceClientController;
use App\Http\Controllers\Api\Admin\ReservationController;
use App\Http\Controllers\Api\Admin\RegleFideliteController;
use App\Http\Controllers\Api\Admin\VarianteCoiffureController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\ReservationAvailabilityController;
use App\Http\Controllers\Api\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/documentation', [DocumentationController::class, 'ui']);
Route::get('/openapi.json', [DocumentationController::class, 'openApi']);
Route::get('/seo/{slug?}', [SeoController::class, 'show'])->where('slug', '.*');
Route::post('/analytics/events', [AnalyticsController::class, 'store']);
Route::get('/reservations/disponibilites', ReservationAvailabilityController::class);

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth.token')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

Route::middleware(['auth.token', 'role:admin', 'log.admin'])
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
        Route::apiResource('gerantes', GeranteController::class)
            ->parameters(['gerantes' => 'gerante']);
        Route::apiResource('parametres-systeme', ParametreSystemeController::class)
            ->parameters(['parametres-systeme' => 'parametreSysteme']);
        Route::apiResource('regles-fidelite', RegleFideliteController::class)
            ->parameters(['regles-fidelite' => 'regleFidelite']);
        Route::apiResource('codes-promo', CodePromoController::class)
            ->parameters(['codes-promo' => 'codePromo']);
        Route::patch('reservations/{reservation}/statut', [ReservationController::class, 'updateStatus'])->name('reservations.status');
        Route::apiResource('reservations', ReservationController::class);
        Route::get('paiements/{paiement}/recu', [PaiementController::class, 'receipt'])->name('paiements.receipt');
        Route::patch('paiements/{paiement}/annuler', [PaiementController::class, 'cancel'])->name('paiements.cancel');
        Route::patch('paiements/{paiement}/recu-envoye', [PaiementController::class, 'markReceiptSent'])->name('paiements.receipt-sent');
        Route::apiResource('paiements', PaiementController::class);
        Route::apiResource('categories-depenses', CategorieDepenseController::class)
            ->parameters(['categories-depenses' => 'categorieDepense']);
        Route::apiResource('depenses', DepenseController::class);
        Route::get('caisses/du-jour', [CaisseController::class, 'today'])->name('caisses.today');
        Route::post('caisses/ouvrir-du-jour', [CaisseController::class, 'openToday'])->name('caisses.open-today');
        Route::patch('caisses/{caisse}/fermer', [CaisseController::class, 'close'])->name('caisses.close');
        Route::apiResource('caisses', CaisseController::class)
            ->parameters(['caisses' => 'caisse']);
        Route::apiResource('mouvements-caisses', MouvementCaisseController::class)
            ->parameters(['mouvements-caisses' => 'mouvementCaisse']);
        Route::patch('clients/{client}/blacklist', [ClientController::class, 'blacklist'])->name('clients.blacklist');
        Route::patch('clients/{client}/unblacklist', [ClientController::class, 'unblacklist'])->name('clients.unblacklist');
        Route::put('clients/{client}/preferences', [PreferenceClientController::class, 'updateForClient'])->name('clients.preferences.update');
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('preferences-clients', PreferenceClientController::class)->only(['index', 'show', 'update'])
            ->parameters(['preferences-clients' => 'preferenceClient']);
        Route::apiResource('liste-noire-clients', ListeNoireClientController::class)->only(['index', 'show', 'update'])
            ->parameters(['liste-noire-clients' => 'listeNoireClient']);
        Route::apiResource('logs-systeme', LogSystemeController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['logs-systeme' => 'logSysteme']);
        Route::apiResource('pages-seo', PageSeoController::class)
            ->parameters(['pages-seo' => 'pageSeo']);
        Route::apiResource('evenements-analytics', EvenementAnalyticsController::class)
            ->only(['index', 'show'])
            ->parameters(['evenements-analytics' => 'evenementAnalytics']);
    });
