<?php

namespace App\Http\Controllers\Api\Gerante;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Reservation;
use App\Services\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    // Transitions autorisees pour la gerante. Plus restrictives que l admin :
    // elle gere le quotidien (accueil, passage en cours, cloture) mais ne
    // peut pas modifier les paiements ni acceder a la caisse.
    private const TRANSITIONS = [
        'en_attente'   => ['confirmee', 'annulee', 'absence'],
        'confirmee'    => ['en_cours', 'annulee', 'absence'],
        'acompte_paye' => ['en_cours', 'terminee', 'annulee', 'absence'],
        'en_cours'     => ['terminee', 'annulee', 'absence'],
        'terminee'     => [],
        'annulee'      => [],
        'absence'      => [],
    ];

    // Transitions sensibles : un acompte a deja ete encaisse. La raison est
    // obligatoire (min 20 car.) pour tracer toute annulation post-paiement
    // et decourager les detournements de caisse.
    private const SENSITIVE_TRANSITIONS = [
        'acompte_paye' => ['annulee', 'absence'],
    ];

    public function __construct(private readonly SystemLogger $logger) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        $reservations = Reservation::query()
            ->with($this->relations())
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')->toString()))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date_reservation', $request->date('date')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('date_reservation', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('date_reservation', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = trim($request->string('search')->toString());
                $q->whereHas('client', function ($cq) use ($search): void {
                    $cq->where('nom', 'ilike', "%{$search}%")
                        ->orWhere('prenom', 'ilike', "%{$search}%")
                        ->orWhere('telephone', 'ilike', "%{$search}%");
                });
                if (ctype_digit($search)) {
                    $q->orWhere('id', (int) $search);
                }
            })
            ->orderByDesc('date_reservation')
            ->orderBy('heure_debut')
            ->paginate($perPage);

        return response()->json(['data' => $reservations]);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'data' => $reservation->load($this->relations()),
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $isSensitive = in_array(
            $request->input('statut'),
            self::SENSITIVE_TRANSITIONS[$reservation->statut] ?? [],
            true
        );

        $isTerminee = $request->input('statut') === 'terminee';
        $hasSolde   = $isTerminee && (float) $reservation->montant_restant > 0;

        $data = $request->validate([
            'statut' => ['required', Rule::in(array_keys(self::TRANSITIONS))],
            // Raison obligatoire uniquement pour les transitions post-acompte
            'raison'  => $isSensitive
                ? ['required', 'string', 'min:20', 'max:1000']
                : ['sometimes', 'nullable', 'string', 'max:1000'],
            // Solde requis quand montant_restant > 0 et passage en terminee
            'enregistrer_paiement' => $hasSolde
                ? ['required', 'boolean']
                : ['sometimes', 'boolean'],
            'mode_paiement_solde'  => ($hasSolde && $request->boolean('enregistrer_paiement'))
                ? ['required', Rule::in(['especes', 'wave', 'orange_money', 'carte_bancaire', 'autre'])]
                : ['sometimes', 'nullable', 'string'],
        ]);

        $allowed = self::TRANSITIONS[$reservation->statut] ?? [];

        if (! in_array($data['statut'], $allowed, true)) {
            throw ValidationException::withMessages([
                'statut' => "Transition non autorisee pour la gerante : {$reservation->statut} → {$data['statut']}.",
            ]);
        }

        $oldStatus = $reservation->statut;
        $newStatus = $data['statut'];

        DB::transaction(function () use ($data, $reservation, $newStatus, $hasSolde): void {
            if ($hasSolde && ($data['enregistrer_paiement'] ?? false)) {
                // UUID temporaire obligatoire car numero_recu est NOT NULL + UNIQUE.
                // On le remplace par le numero definitif apres avoir obtenu l id.
                $paiement = Paiement::query()->create([
                    'reservation_id' => $reservation->id,
                    'client_id'      => $reservation->client_id,
                    'numero_recu'    => 'TEMP-' . Str::uuid()->toString(),
                    'type'           => 'solde',
                    'mode_paiement'  => $data['mode_paiement_solde'],
                    'montant'        => $reservation->montant_restant,
                    'devise'         => $reservation->devise ?? 'FCFA',
                    'statut'         => 'valide',
                    'date_paiement'  => now(),
                    'notes'          => 'Solde encaisse par la gerante a la fin de la prestation.',
                ]);
                $paiement->update([
                    'numero_recu' => 'BT-' . now()->format('Ymd') . '-' . str_pad((string) $paiement->id, 4, '0', STR_PAD_LEFT),
                ]);
                $reservation->montant_restant = 0;
            }

            $reservation->statut = $newStatus;
            $this->applyStatusTimestamps($reservation);
            $reservation->save();
        });

        $this->logStatusChange($request, $reservation, $oldStatus, $newStatus, $data['raison'] ?? null, $isSensitive);

        return response()->json([
            'message' => 'Statut mis a jour.',
            'data'    => $reservation->load($this->relations()),
        ]);
    }

    private function logStatusChange(
        Request     $request,
        Reservation $reservation,
        string      $oldStatus,
        string      $newStatus,
        ?string     $raison,
        bool        $isSensitive,
    ): void {
        // Les transitions sensibles (post-acompte) sont tracees avec une
        // action distincte pour que l admin puisse les filtrer dans les logs.
        $action = $isSensitive ? 'alerte_gerante_annulation_depot' : 'gerante_changement_statut';

        $this->logger->record(
            action: $action,
            module: 'reservations',
            description: "Reservation #{$reservation->id} : {$oldStatus} → {$newStatus}",
            subject: $reservation,
            before: ['statut' => $oldStatus],
            after:  ['statut' => $newStatus],
            metadata: array_filter([
                'reservation_id'    => $reservation->id,
                'client_id'         => $reservation->client_id,
                'montant_acompte'   => $reservation->montant_acompte,
                'raison'            => $raison,
                'actor_role'        => 'gerante',
            ]),
            request: $request,
        );
    }

    private function applyStatusTimestamps(Reservation $reservation): void
    {
        if ($reservation->statut === 'terminee' && ! $reservation->terminee_at) {
            $reservation->terminee_at = now();
        }
        if ($reservation->statut !== 'terminee') {
            $reservation->terminee_at = null;
        }
        if ($reservation->statut === 'annulee' && ! $reservation->annulee_at) {
            $reservation->annulee_at = now();
        }
        if ($reservation->statut !== 'annulee') {
            $reservation->annulee_at = null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'client',
            'coiffeuse',
            'details.coiffure',
            'details.variante',
        ];
    }
}
