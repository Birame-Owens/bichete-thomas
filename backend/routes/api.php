<?php

use App\Http\Controllers\Api\Admin\CategorieCoiffureController;
use App\Http\Controllers\Api\Admin\AvisCoiffureController;
use App\Http\Controllers\Api\Admin\CaisseController;
use App\Http\Controllers\Api\Admin\CodePromoController;
use App\Http\Controllers\Api\Admin\CoiffeuseController;
use App\Http\Controllers\Api\Admin\CoiffureController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\CategorieDepenseController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\DepenseController;
use App\Http\Controllers\Api\Admin\EvenementAnalyticsController;
use App\Http\Controllers\Api\Admin\ExportJournalController;
use App\Http\Controllers\Api\Admin\SignalementController as AdminSignalementController;
use App\Http\Controllers\Api\Gerante\SignalementController as GeranteSignalementController;
use App\Http\Controllers\Api\Admin\GeranteController;
use App\Http\Controllers\Api\Admin\ImageCoiffureController;
use App\Http\Controllers\Api\Gerante\ClientController as GeranteClientController;
use App\Http\Controllers\Api\Gerante\PaiementController as GerantePaiementController;
use App\Http\Controllers\Api\Gerante\ReservationController as GeranteReservationController;
use App\Http\Controllers\Api\Admin\ListeNoireClientController;
use App\Http\Controllers\Api\Admin\LogSystemeController;
use App\Http\Controllers\Api\Admin\MouvementCaisseController;
use App\Http\Controllers\Api\Admin\OptionCoiffureController;
use App\Http\Controllers\Api\Admin\PageSeoController;
use App\Http\Controllers\Api\Admin\PaiementController;
use App\Http\Controllers\Api\Admin\ParametreSystemeController;
use App\Http\Controllers\Api\Admin\PreferenceClientController;
use App\Http\Controllers\Api\Admin\RapportStatistiqueController;
use App\Http\Controllers\Api\Admin\ReservationController;
use App\Http\Controllers\Api\Admin\RegleFideliteController;
use App\Http\Controllers\Api\Admin\VarianteCoiffureController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Client\AvisController as ClientAvisController;
use App\Http\Controllers\Api\Client\CatalogueController as ClientCatalogueController;
use App\Http\Controllers\Api\Client\ClientSessionController;
use App\Http\Controllers\Api\Client\PaymentController as ClientPaymentController;
use App\Http\Controllers\Api\Client\ReservationAvailabilityController as ClientReservationAvailabilityController;
use App\Http\Controllers\Api\Client\ReservationController as ClientReservationController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\SeoController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Documentation Swagger : desactivee en production pour ne pas exposer
// la liste complete des endpoints aux attaquants.
if (! app()->isProduction()) {
    Route::get('/documentation', [DocumentationController::class, 'ui']);
    Route::get('/openapi.json', [DocumentationController::class, 'openApi']);
}
Route::get('/sitemap.xml', [SitemapController::class, 'index']);
Route::get('/seo/{slug?}', [SeoController::class, 'show'])->where('slug', '.*');
Route::post('/analytics/events', [AnalyticsController::class, 'store'])->middleware('throttle:30,1');
Route::get('/reservations/disponibilites', ClientReservationAvailabilityController::class);

Route::prefix('client')->name('client.')->group(function (): void {
    Route::get('/catalogue', [ClientCatalogueController::class, 'index'])->name('catalogue.index');
    Route::get('/catalogue/{coiffure}', [ClientCatalogueController::class, 'show'])->name('catalogue.show');
    Route::get('/promo-active', [ClientCatalogueController::class, 'promoActive'])->name('promo.active');
    // Lookup tel international (Phase 5 etape 1).
    Route::get('/lookup', [ClientCatalogueController::class, 'lookup'])->middleware('throttle:5,1')->name('lookup');
    // Magic link + session client (Phase 5 etape 2).
    Route::post('/auth/login', [ClientSessionController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');
    Route::post('/auth/register', [ClientSessionController::class, 'register'])->middleware('throttle:5,1')->name('auth.register');
    Route::post('/auth/magic-link', [ClientSessionController::class, 'verify'])->middleware('throttle:10,1')->name('auth.magic-link');
    Route::middleware('auth.client.session')->group(function (): void {
        Route::get('/session', [ClientSessionController::class, 'session'])->name('session');
        Route::delete('/session', [ClientSessionController::class, 'logout'])->name('session.logout');
    });
    Route::get('/reservations/disponibilites', ClientReservationAvailabilityController::class)->name('reservations.availability');
    Route::post('/reservations', [ClientReservationController::class, 'store'])->middleware('throttle:10,1')->name('reservations.store');
    Route::post('/paiements/stripe/confirmer', [ClientPaymentController::class, 'confirmStripeCheckout'])->middleware('throttle:20,1')->name('payments.stripe.confirm');
    Route::post('/paiements/stripe/webhook', [ClientPaymentController::class, 'stripeWebhook'])->name('payments.stripe.webhook');
    Route::post('/paiements/paytech/confirmer', [ClientPaymentController::class, 'confirmPaytechReturn'])->middleware('throttle:20,1')->name('payments.paytech.confirm');
    Route::post('/paiements/paytech/ipn', [ClientPaymentController::class, 'paytechWebhook'])->name('payments.paytech.ipn');
    Route::post('/paiements/naboopay/confirmer', [ClientPaymentController::class, 'confirmNaboopayReturn'])->middleware('throttle:20,1')->name('payments.naboopay.confirm');
    Route::post('/paiements/naboopay/webhook', [ClientPaymentController::class, 'naboopayWebhook'])->name('payments.naboopay.webhook');
    // Avis verifies post-prestation (Phase 5 etape 3).
    Route::get('/avis/{token}', [ClientAvisController::class, 'prefill'])->name('avis.prefill');
    Route::post('/avis/{token}', [ClientAvisController::class, 'store'])->middleware('throttle:5,1')->name('avis.store');
});

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

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
        Route::get('rapports-statistiques', RapportStatistiqueController::class)->name('rapports-statistiques');
        Route::get('rapports/export-journal', ExportJournalController::class)->name('rapports.export-journal');
        Route::get('signalements/non-lus-count', [AdminSignalementController::class, 'nonLusCount'])->name('signalements.non-lus-count');
        Route::patch('signalements/{signalement}/marquer-lu', [AdminSignalementController::class, 'marquerLu'])->name('signalements.marquer-lu');
        Route::patch('signalements/{signalement}/marquer-traite', [AdminSignalementController::class, 'marquerTraite'])->name('signalements.marquer-traite');
        Route::apiResource('signalements', AdminSignalementController::class)->only(['index']);
        Route::patch('avis-coiffures/{avisCoiffure}/approuver', [AvisCoiffureController::class, 'approve'])->name('avis-coiffures.approve');
        Route::patch('avis-coiffures/{avisCoiffure}/rejeter', [AvisCoiffureController::class, 'reject'])->name('avis-coiffures.reject');
        Route::apiResource('avis-coiffures', AvisCoiffureController::class)
            ->only(['index', 'show', 'update', 'destroy'])
            ->parameters(['avis-coiffures' => 'avisCoiffure']);
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

// Espace gérante : accès restreint au suivi des réservations du jour.
// Séparé du groupe admin pour ne donner que les permissions nécessaires
// (principe du moindre privilège) et faciliter l'évolution indépendante.
Route::middleware(['auth.token', 'role:gerante', 'log.admin'])
    ->prefix('gerante')
    ->name('gerante.')
    ->group(function (): void {
        Route::get('signalements', [GeranteSignalementController::class, 'index'])->name('signalements.index');
        Route::post('signalements', [GeranteSignalementController::class, 'store'])->name('signalements.store');
        Route::get('reservations', [GeranteReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservations', [GeranteReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservations/{reservation}', [GeranteReservationController::class, 'show'])->name('reservations.show');
        Route::patch('reservations/{reservation}/statut', [GeranteReservationController::class, 'updateStatus'])->name('reservations.statut');
        Route::get('clients', [GeranteClientController::class, 'index'])->name('clients.index');
        Route::post('clients', [GeranteClientController::class, 'store'])->name('clients.store');
        Route::get('clients/{client}', [GeranteClientController::class, 'show'])->name('clients.show');
        Route::put('clients/{client}', [GeranteClientController::class, 'update'])->name('clients.update');
        Route::get('paiements', [GerantePaiementController::class, 'index'])->name('paiements.index');
        Route::get('paiements/{paiement}', [GerantePaiementController::class, 'show'])->name('paiements.show');
    });
