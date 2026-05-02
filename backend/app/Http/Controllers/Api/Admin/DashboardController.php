<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coiffeuse;
use App\Models\Coiffure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'generated_at' => now()->toISOString(),
            'period' => [
                'today' => now()->toDateString(),
            ],
            'kpis' => [
                'chiffre_affaires' => $this->chiffreAffaires(),
                'reservations_du_jour' => $this->reservationsDuJour(),
                'clients_total' => [
                    'available' => true,
                    'value' => Client::query()->count(),
                ],
                'coiffures_total' => [
                    'available' => true,
                    'value' => Coiffure::query()->count(),
                ],
                'coiffeuses_actives' => [
                    'available' => true,
                    'value' => Coiffeuse::query()->where('actif', true)->count(),
                ],
            ],
            'sections' => [
                'paiements_recents' => $this->paiementsRecents(),
                'clients_recents' => $this->clientsRecents(),
                'coiffures_plus_demandees' => $this->coiffuresPlusDemandees(),
                'coiffeuses_plus_productives' => $this->coiffeusesPlusProductives(),
                'depenses_recentes' => $this->depensesRecentes(),
            ],
            'modules_en_attente' => $this->modulesEnAttente(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chiffreAffaires(): array
    {
        if (! Schema::hasTable('paiements')) {
            return $this->unavailable('Module paiements non implemente.');
        }

        return [
            'available' => true,
            'value' => (float) DB::table('paiements')
                ->whereDate('created_at', now()->toDateString())
                ->where('statut', 'valide')
                ->sum('montant'),
            'currency' => 'FCFA',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationsDuJour(): array
    {
        if (! Schema::hasTable('reservations')) {
            return $this->unavailable('Module reservations non implemente.');
        }

        return [
            'available' => true,
            'value' => DB::table('reservations')
                ->whereDate('date_reservation', now()->toDateString())
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paiementsRecents(): array
    {
        if (! Schema::hasTable('paiements')) {
            return $this->unavailable('Module paiements non implemente.');
        }

        return [
            'available' => true,
            'data' => DB::table('paiements')
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientsRecents(): array
    {
        return [
            'available' => true,
            'data' => Client::query()
                ->latest()
                ->limit(5)
                ->get(['id', 'nom', 'prenom', 'telephone', 'email', 'source', 'created_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coiffuresPlusDemandees(): array
    {
        if (! Schema::hasTable('details_reservations')) {
            return $this->unavailable('Module details reservations non implemente.');
        }

        return [
            'available' => true,
            'data' => DB::table('details_reservations')
                ->join('coiffures', 'details_reservations.coiffure_id', '=', 'coiffures.id')
                ->select('coiffures.id', 'coiffures.nom', DB::raw('count(*) as total_reservations'))
                ->groupBy('coiffures.id', 'coiffures.nom')
                ->orderByDesc('total_reservations')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coiffeusesPlusProductives(): array
    {
        if (! Schema::hasTable('reservations')) {
            return $this->unavailable('Module reservations non implemente.');
        }

        return [
            'available' => true,
            'data' => DB::table('reservations')
                ->join('coiffeuses', 'reservations.coiffeuse_id', '=', 'coiffeuses.id')
                ->select('coiffeuses.id', 'coiffeuses.nom', 'coiffeuses.prenom', DB::raw('count(*) as total_reservations'))
                ->where('reservations.statut', 'terminee')
                ->groupBy('coiffeuses.id', 'coiffeuses.nom', 'coiffeuses.prenom')
                ->orderByDesc('total_reservations')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function depensesRecentes(): array
    {
        if (! Schema::hasTable('depenses')) {
            return $this->unavailable('Module depenses non implemente.');
        }

        return [
            'available' => true,
            'data' => DB::table('depenses')
                ->latest('date_depense')
                ->limit(5)
                ->get(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function modulesEnAttente(): array
    {
        $modules = [];

        if (! Schema::hasTable('reservations')) {
            $modules[] = 'reservations';
        }

        if (! Schema::hasTable('paiements')) {
            $modules[] = 'paiements';
        }

        if (! Schema::hasTable('depenses')) {
            $modules[] = 'depenses';
        }

        return $modules;
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(string $message): array
    {
        return [
            'available' => false,
            'value' => null,
            'data' => [],
            'message' => $message,
        ];
    }
}
