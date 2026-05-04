<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coiffeuse;
use App\Models\Coiffure;
use App\Models\LogSysteme;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Query\Builder;
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
        $clientsTotal = $this->clientsTotal();
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
            if (! $this->hasTable('reservations')) {
                return $this->unavailable('Module reservations non implemente.');
            }

            $today = $this->reservationRevenueForDate(now()->toDateString());

            return [
                'available' => true,
                'value' => $today,
                'trend' => $this->trendLabel($today, $this->reservationRevenueForDate(now()->subDay()->toDateString())),
                'currency' => 'FCFA',
            ];
        }

        return [
            'available' => true,
            'value' => $today = (float) DB::table('paiements')
                ->whereDate('created_at', now()->toDateString())
                ->where('statut', 'valide')
                ->sum('montant'),
            'trend' => $this->trendLabel(
                $today,
                (float) DB::table('paiements')
                    ->whereDate('created_at', now()->subDay()->toDateString())
                    ->where('statut', 'valide')
                    ->sum('montant')
            ),
            'currency' => 'FCFA',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function acompteRecu(): array
    {
        if (! $this->hasTable('paiements')) {
            if (! $this->hasTable('reservations')) {
                return $this->unavailable('Module reservations non implemente.');
            }

            $today = $this->reservationDepositsForDate(now()->toDateString());

            return [
                'available' => true,
                'value' => $today,
                'trend' => $this->trendLabel($today, $this->reservationDepositsForDate(now()->subDay()->toDateString())),
                'currency' => 'FCFA',
            ];
        }

        return [
            'available' => true,
            'value' => $today = (float) DB::table('paiements')
                ->whereDate('created_at', now()->toDateString())
                ->where('type', 'acompte')
                ->where('statut', 'valide')
                ->sum('montant'),
            'trend' => $this->trendLabel(
                $today,
                (float) DB::table('paiements')
                    ->whereDate('created_at', now()->subDay()->toDateString())
                    ->where('type', 'acompte')
                    ->where('statut', 'valide')
                    ->sum('montant')
            ),
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
            'value' => $today = DB::table('reservations')
                ->whereDate('date_reservation', now()->toDateString())
                ->count(),
            'trend' => $this->trendLabel(
                (float) $today,
                (float) DB::table('reservations')
                    ->whereDate('date_reservation', now()->subDay()->toDateString())
                    ->count()
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientsTotal(): array
    {
        $todayStart = now()->startOfDay();

        return [
            'available' => true,
            'value' => $total = Client::query()->count(),
            'trend' => $this->trendLabel(
                (float) $total,
                (float) Client::query()->where('created_at', '<', $todayStart)->count()
            ),
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
                ->get(['id', 'action', 'module', 'description', 'metadata', 'created_at'])
                ->map(fn (LogSysteme $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'module' => $log->module,
                    'description' => $this->activityDescription($log),
                    'created_at' => $log->created_at?->toISOString(),
                ])
                ->values(),
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
            'trend' => $source['available'] ? ($source['trend'] ?? null) : ($source['message'] ?? 'Module non implemente.'),
        ];
    }

    private function reservationPreviewQuery(): Builder
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

    private function trendLabel(float $current, float $previous): string
    {
        if ($previous <= 0.0) {
            return $current > 0.0 ? '+100% vs hier' : '0% vs hier';
        }

        $percent = (($current - $previous) / $previous) * 100;
        $prefix = $percent > 0 ? '+' : '';

        return sprintf('%s%s%% vs hier', $prefix, rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.'));
    }

    private function reservationRevenueForDate(string $date): float
    {
        return (float) DB::table('reservations')
            ->whereDate('date_reservation', $date)
            ->whereNotIn('statut', ['annulee', 'absence'])
            ->sum('montant_total');
    }

    private function reservationDepositsForDate(string $date): float
    {
        return (float) DB::table('reservations')
            ->whereDate('date_reservation', $date)
            ->whereIn('statut', ['acompte_paye', 'en_cours', 'terminee'])
            ->sum('montant_acompte');
    }

    private function activityDescription(LogSysteme $log): string
    {
        $path = (string) ($log->metadata['path'] ?? $log->description ?? '');
        $method = strtoupper((string) ($log->metadata['method'] ?? ''));

        if ($method === '' && preg_match('/^(POST|PUT|PATCH|DELETE)\s+(.+)$/', $path, $matches) === 1) {
            $method = $matches[1];
            $path = $matches[2];
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));

        if (($segments[0] ?? null) === 'api') {
            array_shift($segments);
        }

        if (($segments[0] ?? null) === 'admin') {
            array_shift($segments);
        }

        $resource = $segments[0] ?? $log->module ?? 'action';
        $id = $segments[1] ?? null;
        $subAction = $segments[2] ?? null;

        if ($resource === 'reservations') {
            if ($subAction === 'statut' && $id) {
                return "Statut de la reservation #{$id} mis a jour";
            }

            return match ($method) {
                'POST' => 'Nouvelle reservation creee',
                'PUT', 'PATCH' => $id ? "Reservation #{$id} modifiee" : 'Reservation modifiee',
                'DELETE' => $id ? "Reservation #{$id} supprimee" : 'Reservation supprimee',
                default => $id ? "Reservation #{$id} mise a jour" : 'Reservation mise a jour',
            };
        }

        $labels = [
            'clients' => ['singular' => 'Client', 'created' => 'Nouveau client cree'],
            'coiffeuses' => ['singular' => 'Coiffeuse', 'created' => 'Nouvelle coiffeuse creee'],
            'gerantes' => ['singular' => 'Gerante', 'created' => 'Nouvelle gerante creee'],
            'coiffures' => ['singular' => 'Coiffure', 'created' => 'Nouvelle coiffure creee'],
            'categories-coiffures' => ['singular' => 'Categorie coiffure', 'created' => 'Nouvelle categorie coiffure creee'],
            'options-coiffures' => ['singular' => 'Option coiffure', 'created' => 'Nouvelle option coiffure creee'],
            'codes-promo' => ['singular' => 'Code promo', 'created' => 'Nouveau code promo cree'],
            'regles-fidelite' => ['singular' => 'Regle fidelite', 'created' => 'Nouvelle regle fidelite creee'],
            'parametres-systeme' => ['singular' => 'Parametre', 'created' => 'Parametre cree'],
        ];

        $label = $labels[$resource] ?? ['singular' => ucfirst(str_replace('-', ' ', $resource)), 'created' => 'Nouvelle action admin'];

        return match ($method) {
            'POST' => $label['created'],
            'PUT', 'PATCH' => $id ? "{$label['singular']} #{$id} mis a jour" : "{$label['singular']} mis a jour",
            'DELETE' => $id ? "{$label['singular']} #{$id} supprime" : "{$label['singular']} supprime",
            default => $log->description ?? 'Action admin effectuee',
        };
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
