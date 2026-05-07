<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RapportStatistiqueController extends Controller
{
    /**
     * @var array<string, bool>
     */
    private array $tableExists = [];

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:today,week,month,year,custom'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        [$start, $end, $label] = $this->period($request);
        [$previousStart, $previousEnd] = $this->previousPeriod($start, $end);

        $chiffreAffaires = $this->chiffreAffaires($start, $end);
        $previousChiffreAffaires = $this->chiffreAffaires($previousStart, $previousEnd);
        $depenses = $this->depenses($start, $end);
        $previousDepenses = $this->depenses($previousStart, $previousEnd);
        $reservations = $this->reservations($start, $end);
        $previousReservations = $this->reservations($previousStart, $previousEnd);
        $clients = $this->clients($start, $end);
        $previousClients = $this->clients($previousStart, $previousEnd);
        $annulations = $this->annulations($start, $end);
        $previousAnnulations = $this->annulations($previousStart, $previousEnd);

        return response()->json([
            'generated_at' => now()->toISOString(),
            'period' => [
                'label' => $label,
                'date_debut' => $start->toDateString(),
                'date_fin' => $end->toDateString(),
                'previous_date_debut' => $previousStart->toDateString(),
                'previous_date_fin' => $previousEnd->toDateString(),
            ],
            'summary' => [
                'chiffre_affaires' => $this->metric($chiffreAffaires, $previousChiffreAffaires, 'money'),
                'depenses' => $this->metric($depenses, $previousDepenses, 'money'),
                'benefice' => $this->metric($chiffreAffaires - $depenses, $previousChiffreAffaires - $previousDepenses, 'money'),
                'reservations' => $this->metric($reservations, $previousReservations, 'number'),
                'nouveaux_clients' => $this->metric($clients, $previousClients, 'number'),
                'panier_moyen' => $this->metric($this->panierMoyen($start, $end), $this->panierMoyen($previousStart, $previousEnd), 'money'),
                'taux_annulation' => $this->metric($this->rate($annulations, $reservations), $this->rate($previousAnnulations, $previousReservations), 'percent'),
            ],
            'series' => [
                'granularity' => $this->granularity($start, $end),
                'chiffre_affaires' => $this->chiffreAffairesSeries($start, $end),
                'depenses' => $this->depensesSeries($start, $end),
                'reservations' => $this->reservationsSeries($start, $end),
            ],
            'breakdowns' => [
                'paiements_par_mode' => $this->paiementsParMode($start, $end),
                'depenses_par_categorie' => $this->depensesParCategorie($start, $end),
                'reservations_par_statut' => $this->reservationsParStatut($start, $end),
            ],
            'tops' => [
                'coiffures' => $this->topCoiffures($start, $end),
                'clients' => $this->topClients($start, $end),
                'coiffeuses' => $this->topCoiffeuses($start, $end),
            ],
        ]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function period(Request $request): array
    {
        $now = CarbonImmutable::now();
        $period = $request->string('period', 'month')->toString();

        if ($period === 'today') {
            return [$now->startOfDay(), $now->endOfDay(), 'Aujourd hui'];
        }

        if ($period === 'week') {
            return [$now->startOfWeek()->startOfDay(), $now->endOfWeek()->endOfDay(), 'Cette semaine'];
        }

        if ($period === 'year') {
            return [$now->startOfYear()->startOfDay(), $now->endOfYear()->endOfDay(), 'Cette annee'];
        }

        if ($period === 'custom') {
            $start = $request->filled('date_debut')
                ? CarbonImmutable::parse($request->date('date_debut'))->startOfDay()
                : $now->subDays(29)->startOfDay();
            $end = $request->filled('date_fin')
                ? CarbonImmutable::parse($request->date('date_fin'))->endOfDay()
                : $now->endOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
            }

            return [$start, $end, 'Periode personnalisee'];
        }

        return [$now->startOfMonth()->startOfDay(), $now->endOfMonth()->endOfDay(), 'Ce mois'];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function previousPeriod(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $days = (int) $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay()->endOfDay();

        return [$previousEnd->subDays($days - 1)->startOfDay(), $previousEnd];
    }

    /**
     * @return array<string, mixed>
     */
    private function metric(float|int $current, float|int $previous, string $format): array
    {
        return [
            'value' => round((float) $current, 2),
            'previous' => round((float) $previous, 2),
            'format' => $format,
            'trend' => $this->trend($current, $previous),
        ];
    }

    private function trend(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0.0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);
    }

    private function rate(float|int $part, float|int $total): float
    {
        if ((float) $total <= 0.0) {
            return 0.0;
        }

        return round(((float) $part / (float) $total) * 100, 1);
    }

    private function chiffreAffaires(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! $this->hasTable('paiements')) {
            return 0.0;
        }

        return (float) DB::table('paiements')
            ->whereBetween('date_paiement', [$start, $end])
            ->where('statut', 'valide')
            ->whereIn('type', ['acompte', 'solde', 'complet', 'ajustement'])
            ->sum('montant');
    }

    private function depenses(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! $this->hasTable('depenses')) {
            return 0.0;
        }

        return (float) DB::table('depenses')
            ->whereBetween('date_depense', [$start->toDateString(), $end->toDateString()])
            ->sum('montant');
    }

    private function reservations(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (! $this->hasTable('reservations')) {
            return 0;
        }

        return (int) DB::table('reservations')
            ->whereBetween('date_reservation', [$start->toDateString(), $end->toDateString()])
            ->count();
    }

    private function annulations(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (! $this->hasTable('reservations')) {
            return 0;
        }

        return (int) DB::table('reservations')
            ->whereBetween('date_reservation', [$start->toDateString(), $end->toDateString()])
            ->whereIn('statut', ['annulee', 'absence'])
            ->count();
    }

    private function clients(CarbonImmutable $start, CarbonImmutable $end): int
    {
        if (! $this->hasTable('clients')) {
            return 0;
        }

        return (int) DB::table('clients')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    private function panierMoyen(CarbonImmutable $start, CarbonImmutable $end): float
    {
        if (! $this->hasTable('reservations')) {
            return 0.0;
        }

        return (float) DB::table('reservations')
            ->whereBetween('date_reservation', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('statut', ['annulee', 'absence'])
            ->avg('montant_total');
    }

    private function granularity(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $days = (int) $start->diffInDays($end) + 1;

        if ($days > 120) {
            return 'month';
        }

        if ($days > 45) {
            return 'week';
        }

        return 'day';
    }

    /**
     * @return array<int, array{key: string, label: string, value: float}>
     */
    private function chiffreAffairesSeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('paiements')) {
            return $this->emptySeries($start, $end);
        }

        $rows = DB::table('paiements')
            ->whereBetween('date_paiement', [$start, $end])
            ->where('statut', 'valide')
            ->whereIn('type', ['acompte', 'solde', 'complet', 'ajustement'])
            ->get(['date_paiement as date_value', 'montant as value']);

        return $this->seriesFromRows($start, $end, $rows->all());
    }

    /**
     * @return array<int, array{key: string, label: string, value: float}>
     */
    private function depensesSeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('depenses')) {
            return $this->emptySeries($start, $end);
        }

        $rows = DB::table('depenses')
            ->whereBetween('date_depense', [$start->toDateString(), $end->toDateString()])
            ->get(['date_depense as date_value', 'montant as value']);

        return $this->seriesFromRows($start, $end, $rows->all());
    }

    /**
     * @return array<int, array{key: string, label: string, value: float}>
     */
    private function reservationsSeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('reservations')) {
            return $this->emptySeries($start, $end);
        }

        $rows = DB::table('reservations')
            ->whereBetween('date_reservation', [$start->toDateString(), $end->toDateString()])
            ->get(['date_reservation as date_value']);

        return $this->seriesFromRows($start, $end, $rows->map(fn (object $row): object => (object) [
            'date_value' => $row->date_value,
            'value' => 1,
        ])->all());
    }

    /**
     * @return array<int, array{key: string, label: string, value: float}>
     */
    private function emptySeries(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return array_values($this->initialBuckets($start, $end));
    }

    /**
     * @param array<int, object> $rows
     * @return array<int, array{key: string, label: string, value: float}>
     */
    private function seriesFromRows(CarbonImmutable $start, CarbonImmutable $end, array $rows): array
    {
        $buckets = $this->initialBuckets($start, $end);

        foreach ($rows as $row) {
            $date = CarbonImmutable::parse($row->date_value);
            $key = $this->bucketKey($date, $this->granularity($start, $end));

            if (isset($buckets[$key])) {
                $buckets[$key]['value'] += (float) ($row->value ?? 0);
            }
        }

        return array_values($buckets);
    }

    /**
     * @return array<string, array{key: string, label: string, value: float}>
     */
    private function initialBuckets(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $granularity = $this->granularity($start, $end);
        $cursor = match ($granularity) {
            'month' => $start->startOfMonth(),
            'week' => $start->startOfWeek(),
            default => $start->startOfDay(),
        };
        $limit = $end->endOfDay();
        $buckets = [];

        while ($cursor->lessThanOrEqualTo($limit)) {
            $key = $this->bucketKey($cursor, $granularity);
            $buckets[$key] = [
                'key' => $key,
                'label' => $this->bucketLabel($cursor, $granularity),
                'value' => 0.0,
            ];
            $cursor = match ($granularity) {
                'month' => $cursor->addMonth()->startOfMonth(),
                'week' => $cursor->addWeek()->startOfWeek(),
                default => $cursor->addDay()->startOfDay(),
            };
        }

        return $buckets;
    }

    private function bucketKey(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'month' => $date->startOfMonth()->toDateString(),
            'week' => $date->startOfWeek()->toDateString(),
            default => $date->toDateString(),
        };
    }

    private function bucketLabel(CarbonImmutable $date, string $granularity): string
    {
        return match ($granularity) {
            'month' => $date->translatedFormat('M Y'),
            'week' => $date->translatedFormat('d M'),
            default => $date->translatedFormat('d M'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, amount: float, percent: float}>
     */
    private function paiementsParMode(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('paiements')) {
            return [];
        }

        $rows = DB::table('paiements')
            ->select('mode_paiement', DB::raw('SUM(montant) as total'))
            ->whereBetween('date_paiement', [$start, $end])
            ->where('statut', 'valide')
            ->whereIn('type', ['acompte', 'solde', 'complet', 'ajustement'])
            ->groupBy('mode_paiement')
            ->orderByDesc('total')
            ->get();
        $total = max((float) $rows->sum('total'), 1.0);

        return $rows->map(fn (object $row): array => [
            'key' => (string) $row->mode_paiement,
            'label' => $this->paymentMethodLabel((string) $row->mode_paiement),
            'amount' => (float) $row->total,
            'percent' => round(((float) $row->total / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @return array<int, array{key: string, label: string, amount: float, percent: float}>
     */
    private function depensesParCategorie(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('depenses')) {
            return [];
        }

        $rows = DB::table('depenses')
            ->leftJoin('categories_depenses', 'depenses.categorie_depense_id', '=', 'categories_depenses.id')
            ->select(DB::raw("COALESCE(categories_depenses.nom, 'Sans categorie') as categorie"), DB::raw('SUM(depenses.montant) as total'))
            ->whereBetween('depenses.date_depense', [$start->toDateString(), $end->toDateString()])
            ->groupByRaw("COALESCE(categories_depenses.nom, 'Sans categorie')")
            ->orderByDesc('total')
            ->get();
        $total = max((float) $rows->sum('total'), 1.0);

        return $rows->map(fn (object $row): array => [
            'key' => (string) $row->categorie,
            'label' => (string) $row->categorie,
            'amount' => (float) $row->total,
            'percent' => round(((float) $row->total / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @return array<int, array{key: string, label: string, count: int, percent: float}>
     */
    private function reservationsParStatut(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('reservations')) {
            return [];
        }

        $rows = DB::table('reservations')
            ->select('statut', DB::raw('COUNT(*) as total'))
            ->whereBetween('date_reservation', [$start->toDateString(), $end->toDateString()])
            ->groupBy('statut')
            ->orderByDesc('total')
            ->get();
        $total = max((float) $rows->sum('total'), 1.0);

        return $rows->map(fn (object $row): array => [
            'key' => (string) $row->statut,
            'label' => $this->reservationStatusLabel((string) $row->statut),
            'count' => (int) $row->total,
            'percent' => round(((float) $row->total / $total) * 100, 1),
        ])->values()->all();
    }

    /**
     * @return array<int, array{name: string, reservations: int, amount: float}>
     */
    private function topCoiffures(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('details_reservations') || ! $this->hasTable('reservations')) {
            return [];
        }

        return DB::table('details_reservations')
            ->join('reservations', 'details_reservations.reservation_id', '=', 'reservations.id')
            ->select('details_reservations.coiffure_nom', DB::raw('COUNT(*) as total'), DB::raw('SUM(details_reservations.montant_total) as amount'))
            ->whereBetween('reservations.date_reservation', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('reservations.statut', ['annulee', 'absence'])
            ->groupBy('details_reservations.coiffure_nom')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->coiffure_nom,
                'reservations' => (int) $row->total,
                'amount' => (float) $row->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, reservations: int, amount: float}>
     */
    private function topClients(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('reservations') || ! $this->hasTable('clients')) {
            return [];
        }

        return DB::table('reservations')
            ->join('clients', 'reservations.client_id', '=', 'clients.id')
            ->select('clients.nom', 'clients.prenom', DB::raw('COUNT(*) as total'), DB::raw('SUM(reservations.montant_total) as amount'))
            ->whereBetween('reservations.date_reservation', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('reservations.statut', ['annulee', 'absence'])
            ->groupBy('clients.id', 'clients.nom', 'clients.prenom')
            ->orderByDesc('amount')
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => [
                'name' => trim("{$row->prenom} {$row->nom}") ?: 'Client',
                'reservations' => (int) $row->total,
                'amount' => (float) $row->amount,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{name: string, reservations: int, amount: float}>
     */
    private function topCoiffeuses(CarbonImmutable $start, CarbonImmutable $end): array
    {
        if (! $this->hasTable('reservations') || ! $this->hasTable('coiffeuses')) {
            return [];
        }

        return DB::table('reservations')
            ->join('coiffeuses', 'reservations.coiffeuse_id', '=', 'coiffeuses.id')
            ->select('coiffeuses.nom', 'coiffeuses.prenom', DB::raw('COUNT(*) as total'), DB::raw('SUM(reservations.montant_total) as amount'))
            ->whereBetween('reservations.date_reservation', [$start->toDateString(), $end->toDateString()])
            ->where('reservations.statut', 'terminee')
            ->groupBy('coiffeuses.id', 'coiffeuses.nom', 'coiffeuses.prenom')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => [
                'name' => trim("{$row->prenom} {$row->nom}") ?: 'Coiffeuse',
                'reservations' => (int) $row->total,
                'amount' => (float) $row->amount,
            ])
            ->values()
            ->all();
    }

    private function paymentMethodLabel(string $method): string
    {
        return [
            'especes' => 'Especes',
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'carte_bancaire' => 'Carte bancaire',
            'virement' => 'Virement',
            'autre' => 'Autre',
        ][$method] ?? ucfirst(str_replace('_', ' ', $method));
    }

    private function reservationStatusLabel(string $status): string
    {
        return [
            'en_attente' => 'En attente',
            'confirmee' => 'Confirmee',
            'acompte_paye' => 'Acompte paye',
            'en_cours' => 'En cours',
            'terminee' => 'Terminee',
            'annulee' => 'Annulee',
            'absence' => 'Absence',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }
}
