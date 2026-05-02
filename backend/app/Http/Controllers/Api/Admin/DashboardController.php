<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coiffeuse;
use App\Models\Coiffure;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        // Stats available from existing modules
        $totalClients = Client::query()->count();
        $totalCoiffeusesActives = Coiffeuse::query()->where('actif', true)->count();
        $totalCoiffuresActives = Coiffure::query()->where('actif', true)->count();

        $clientsRecents = Client::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'nom', 'prenom', 'telephone', 'source', 'created_at']);

        $coiffeusesActives = Coiffeuse::query()
            ->where('actif', true)
            ->orderBy('nom')
            ->orderBy('prenom')
            ->limit(10)
            ->get(['id', 'nom', 'prenom', 'telephone', 'pourcentage_commission']);

        return response()->json([
            // Available stats
            'total_clients' => $totalClients,
            'total_coiffeuses_actives' => $totalCoiffeusesActives,
            'total_coiffures_actives' => $totalCoiffuresActives,
            'clients_recents' => $clientsRecents,
            'coiffeuses_actives' => $coiffeusesActives,

            // Stats pending future modules (reservations, paiements, dépenses)
            'chiffre_affaires' => null,
            'reservations_aujourd_hui' => null,
            'paiements_recents' => null,
            'coiffures_populaires' => null,
            'coiffeuses_productives' => null,
            'depenses_recentes' => null,
        ]);
    }
}
