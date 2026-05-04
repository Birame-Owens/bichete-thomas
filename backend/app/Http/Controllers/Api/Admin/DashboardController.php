<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coiffeuse;
use App\Models\Coiffure;
use App\Models\LogSysteme;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * @var array<string, bool>
     */
    private array $tableExists = [];

    public function __invoke(): JsonResponse
    {
        $chiffreAffaires = $this->chiffreAffaires();
        $reservationsDuJour = $this->reservationsDuJour();
        $clientsTotal = ['available' => true, 'value' => Client::query()->count()];
        $acompteRecu = $this->acompteRecu();
        $clientsRecents = $this->clientsRecents();
        $depensesRecentes = $this->depensesRecentes();

        return response()->json([
            'generated_at' => now()->toISOString(),
            'period' => [
                'today' => now()->toDateString(),
                'label' => now()->translatedFormat('d F Y'),
            ],
            'cards' => [
                $this->card('chiffre_affaires', 'Chiffre d affaires', $chiffreAffaires, 'money', '#e91e63', '$'),
                $this->card('reservations', 'Reservations', $reservationsDuJour, 'number', '#b719c9', 'R'),
                $this->card('clients', 'Clients', $clientsTotal, 'number', '#f51b7a', 'C'),
                $this->card('acompte_recu', 'Acompte recu', $acompteRecu, 'money', '#f5a623', '$'),
            ],
            'charts' => [
                'chiffre_affaires' => $this->chiffreAffairesSemaine(),
                'top_coiffures' => $this->topCoiffuresReservees(),
            ],
            'lists' => [
                'reservations_du_jour' => $this->reservationsDuJourListe(),
                'dernieres_reservations' => $this->dernieresReservations(),
                'activite_recente' => $this->activiteRecente(),
                'depenses_recentes' => $depensesRecentes,
                'clients_recents' => $clientsRecents,
            ],
            'payments' => [
                'repartition' => $this->repartitionPaiements(),
            ],
            'quick_payment' => [
                'available' => $this->hasTable('paiements'),
                'message' => $this->hasTable('paiements') ? null : 'Module paiements non implemente.',
                'methods' => ['especes', 'wave', 'orange_money', 'carte_bancaire'],
            ],
            'promo' => $this->promoPrincipale(),
            'kpis' => [
                'chiffre_affaires' => $chiffreAffaires,
                'reservations_du_jour' => $reservationsDuJour,
                'clients_total' => $clientsTotal,
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
                'clients_recents' => $clientsRecents,
                'coiffures_plus_demandees' => $this->coiffuresPlusDemandees(),
                'coiffeuses_plus_productives' => $this->coiffeusesPlusProductives(),
                'depenses_recentes' => $depensesRecentes,
            ],
            'modules_en_attente' => $this->modulesEnAttente(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function chiffreAffaires(): array
    {
        if (! $this->hasTable('paiements')) {
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
    private function acompteRecu(): array
    {
        if (! $this->hasTable('paiements')) {
            return $this->unavailable('Module paiements non implemente.');
        }

        return [
            'available' => true,
            'value' => (float) DB::table('paiements')
                ->whereDate('created_at', now()->toDateString())
                ->where('type', 'acompte')
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
        if (! $this->hasTable('reservations')) {
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
        if (! $this->hasTable('paiements')) {
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
    private function chiffreAffairesSemaine(): array
    {
        if (! $this->hasTable('paiements')) {
            return $this->unavailable('Module paiements non implemente.');
        }

        $start = now()->startOfWeek();
        $rows = DB::table('paiements')
            ->selectRaw('DATE(created_at) as date, SUM(montant) as total')
            ->where('statut', 'valide')
            ->whereBetween('created_at', [$start, now()->endOfWeek()])
            ->groupBy('date')
            ->pluck('total', 'date');

        return [
            'available' => true,
            'data' => collect(range(0, 6))->map(function (int $day) use ($start, $rows): array {
                $date = $start->copy()->addDays($day);

                return [
                    'label' => $date->translatedFormat('D'),
                    'date' => $date->toDateString(),
                    'value' => (float) ($rows[$date->toDateString()] ?? 0),
                ];
            })->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topCoiffuresReservees(): array
    {
        if (! $this->hasTable('details_reservations')) {
            return $this->unavailable('Module details reservations non implemente.');
        }

        $rows = DB::table('details_reservations')
            ->join('coiffures', 'details_reservations.coiffure_id', '=', 'coiffures.id')
            ->select('coiffures.nom', DB::raw('count(*) as total'))
            ->groupBy('coiffures.nom')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $total = max((int) $rows->sum('total'), 1);

        return [
            'available' => true,
            'data' => $rows->map(fn ($row): array => [
                'name' => $row->nom,
                'total' => (int) $row->total,
                'percent' => round(((int) $row->total / $total) * 100),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationsDuJourListe(): array
    {
        if (! $this->hasTable('reservations')) {
            return $this->unavailable('Module reservations non implemente.');
        }

        return [
            'available' => true,
            'data' => $this->reservationPreviewQuery()
                ->whereDate('date_reservation', now()->toDateString())
                ->orderBy('reservations.heure_debut')
                ->limit(4)
                ->get()
                ->map(fn ($reservation): array => $this->formatReservationPreview($reservation))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dernieresReservations(): array
    {
        if (! $this->hasTable('reservations')) {
            return $this->unavailable('Module reservations non implemente.');
        }

        return [
            'available' => true,
            'data' => $this->reservationPreviewQuery()
                ->orderByDesc('reservations.created_at')
                ->limit(5)
                ->get()
                ->map(fn ($reservation): array => $this->formatReservationPreview($reservation))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function repartitionPaiements(): array
    {
        if (! $this->hasTable('paiements')) {
            return $this->unavailable('Module paiements non implemente.');
        }

        $rows = DB::table('paiements')
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->where('statut', 'valide')
            ->groupBy('mode_paiement')
            ->get();

        $total = max((float) $rows->sum('total'), 1);

        return [
            'available' => true,
            'data' => $rows->map(fn ($row): array => [
                'method' => $row->mode_paiement,
                'amount' => (float) $row->total,
                'percent' => round(((float) $row->total / $total) * 100),
            ]),
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
    private function activiteRecente(): array
    {
        if (! $this->hasTable('logs_systeme')) {
            return $this->unavailable('Module logs systeme non implemente.');
        }

        return [
            'available' => true,
            'data' => LogSysteme::query()
                ->latest('created_at')
                ->limit(5)
                ->get(['id', 'action', 'module', 'description', 'created_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function coiffuresPlusDemandees(): array
    {
        if (! $this->hasTable('details_reservations')) {
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
        if (! $this->hasTable('reservations')) {
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
        if (! $this->hasTable('depenses')) {
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

        if (! $this->hasTable('reservations')) {
            $modules[] = 'reservations';
        }

        if (! $this->hasTable('paiements')) {
            $modules[] = 'paiements';
        }

        if (! $this->hasTable('depenses')) {
            $modules[] = 'depenses';
        }

        return $modules;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function card(string $key, string $label, array $source, string $format, string $color, string $icon): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'available' => $source['available'],
            'value' => $source['value'] ?? null,
            'message' => $source['message'] ?? null,
            'format' => $format,
            'color' => $color,
            'icon' => $icon,
            'trend' => $source['available'] ? null : ($source['message'] ?? 'Module non implemente.'),
        ];
    }

    private function reservationPreviewQuery(): \Illuminate\Database\Query\Builder
    {
        $firstDetails = DB::table('details_reservations')
            ->select('reservation_id', DB::raw('MIN(id) as detail_id'))
            ->groupBy('reservation_id');

        return DB::table('reservations')
            ->leftJoin('clients', 'reservations.client_id', '=', 'clients.id')
            ->leftJoinSub($firstDetails, 'first_details', function ($join): void {
                $join->on('reservations.id', '=', 'first_details.reservation_id');
            })
            ->leftJoin('details_reservations', 'first_details.detail_id', '=', 'details_reservations.id')
            ->select([
                'reservations.id',
                'reservations.date_reservation',
                'reservations.heure_debut',
                'reservations.statut',
                'reservations.montant_total',
                'reservations.devise',
                'clients.nom as client_nom',
                'clients.prenom as client_prenom',
                'clients.telephone as client_telephone',
                'details_reservations.coiffure_nom',
                'details_reservations.variante_nom',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReservationPreview(object $reservation): array
    {
        $clientName = trim(sprintf('%s %s', $reservation->client_prenom ?? '', $reservation->client_nom ?? ''));

        return [
            'id' => $reservation->id,
            'date_reservation' => $reservation->date_reservation,
            'heure_debut' => substr((string) $reservation->heure_debut, 0, 5),
            'statut' => $reservation->statut,
            'montant_total' => (float) $reservation->montant_total,
            'devise' => $reservation->devise ?? 'FCFA',
            'client_nom' => $reservation->client_nom,
            'client_prenom' => $reservation->client_prenom,
            'client_telephone' => $reservation->client_telephone,
            'client' => $clientName !== '' ? $clientName : 'Client supprime',
            'coiffure' => $reservation->coiffure_nom ?? 'Prestation',
            'variante' => $reservation->variante_nom,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promoPrincipale(): array
    {
        if (! $this->hasTable('codes_promo')) {
            return $this->unavailable('Module codes promo non implemente.');
        }

        $promo = DB::table('codes_promo')
            ->where('actif', true)
            ->orderByDesc('created_at')
            ->first();

        return [
            'available' => $promo !== null,
            'data' => $promo,
            'message' => $promo ? null : 'Aucun code promo actif.',
        ];
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
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
