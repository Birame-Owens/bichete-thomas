<?php

namespace App\Http\Controllers\Api\Gerante;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coiffure;
use App\Models\Paiement;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use App\Services\SystemLogger;
use Carbon\Carbon;
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

    private const BLOCKING_STATUSES = [
        'en_attente', 'confirmee', 'acompte_paye', 'en_cours', 'terminee',
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

    public function store(Request $request): JsonResponse
    {
        $enregistrerAcompte = $request->boolean('enregistrer_acompte', false);

        $data = $request->validate([
            'client_id'                      => ['required', 'integer', 'exists:clients,id'],
            'coiffeuse_id'                   => ['nullable', 'integer', Rule::exists('coiffeuses', 'id')->where('actif', true)],
            'date_reservation'               => ['required', 'date', 'after_or_equal:today'],
            'heure_debut'                    => ['required', 'date_format:H:i'],
            'details'                        => ['required', 'array', 'min:1'],
            'details.*.coiffure_id'          => ['required', 'integer', 'exists:coiffures,id'],
            'details.*.variante_coiffure_id' => ['required', 'integer', 'exists:variantes_coiffures,id'],
            'details.*.quantite'             => ['sometimes', 'integer', 'min:1', 'max:10'],
            'notes'                          => ['nullable', 'string', 'max:5000'],
            'enregistrer_acompte'            => ['sometimes', 'boolean'],
            'mode_paiement_acompte'          => [
                $enregistrerAcompte ? 'required' : 'sometimes',
                'nullable',
                Rule::in(['especes', 'wave', 'orange_money', 'carte_bancaire', 'autre']),
            ],
        ]);

        $client = Client::query()->find($data['client_id']);

        if ($client->est_blackliste) {
            throw ValidationException::withMessages([
                'client_id' => 'Cette cliente est dans la liste noire.',
            ]);
        }

        // Calcul du total et de la duree a partir des coiffures/variantes choisies
        $details  = [];
        $subtotal = 0.0;
        $duration = 0;

        foreach (array_values($data['details']) as $index => $detailData) {
            $line      = $this->computeDetail($detailData, $index + 1);
            $details[] = $line;
            $subtotal  += (float) $line['montant_total'];
            $duration  += (int) $line['duree_minutes'];
        }

        if ($duration < 1) {
            throw ValidationException::withMessages([
                'details' => 'La reservation doit contenir au moins une prestation valide.',
            ]);
        }

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $data['date_reservation'] . ' ' . $data['heure_debut']);
        $endsAt   = $startsAt->copy()->addMinutes($duration);

        // Creneau dans le passe sur le jour courant : interdit meme si la date est valide.
        if ($startsAt->isPast()) {
            throw ValidationException::withMessages([
                'heure_debut' => 'Le creneau selectionne est deja passe.',
            ]);
        }

        if (! $startsAt->isSameDay($endsAt)) {
            throw ValidationException::withMessages([
                'heure_debut' => 'La reservation doit se terminer le meme jour.',
            ]);
        }

        $this->ensureOpenDay($startsAt);
        $this->ensureWithinBusinessHours($startsAt, $endsAt);
        $this->ensureSalonCapacity($data, null);

        // Acompte cible calcule par les parametres systeme
        $depositPercent  = (float) $this->settingValue('pourcentage_acompte', 0);
        $depositFallback = (float) $this->settingValue('montant_acompte_defaut', 0);
        $deposit         = $depositPercent > 0
            ? round($subtotal * ($depositPercent / 100), 2)
            : min($depositFallback, $subtotal);
        $deposit = min(max($deposit, 0), $subtotal);

        $reservation = DB::transaction(function () use ($data, $details, $subtotal, $duration, $deposit, $endsAt): Reservation {
            // La source est toujours physique : la gerante gere uniquement
            // les clientes qui se presentent en personne au salon.
            $statut = ($data['enregistrer_acompte'] ?? false) && $deposit > 0
                ? 'acompte_paye'
                : 'en_attente';

            $res = Reservation::query()->create([
                'client_id'            => $data['client_id'],
                'coiffeuse_id'         => $data['coiffeuse_id'] ?? null,
                'date_reservation'     => $data['date_reservation'],
                'heure_debut'          => $data['heure_debut'],
                'heure_fin'            => $endsAt->format('H:i'),
                'duree_totale_minutes' => $duration,
                'statut'               => $statut,
                'source'               => 'physique',
                'montant_total'        => round($subtotal, 2),
                'montant_reduction'    => 0,
                'montant_acompte'      => round($deposit, 2),
                'montant_restant'      => round(max($subtotal - $deposit, 0), 2),
                'devise'               => 'FCFA',
                'fidelite_appliquee'   => false,
                'notes'                => $data['notes'] ?? null,
            ]);

            foreach ($details as $detail) {
                $res->details()->create($detail);
            }

            if ($statut === 'acompte_paye') {
                // UUID temporaire obligatoire car numero_recu est NOT NULL + UNIQUE.
                // On le remplace par le numero definitif apres avoir obtenu l id.
                $paiement = Paiement::query()->create([
                    'reservation_id' => $res->id,
                    'client_id'      => $res->client_id,
                    'numero_recu'    => 'TEMP-' . Str::uuid()->toString(),
                    'type'           => 'acompte',
                    'mode_paiement'  => $data['mode_paiement_acompte'],
                    'montant'        => $res->montant_acompte,
                    'devise'         => 'FCFA',
                    'statut'         => 'valide',
                    'date_paiement'  => now(),
                    'notes'          => 'Acompte encaisse par la gerante a la creation de la reservation.',
                ]);
                $paiement->update([
                    'numero_recu' => 'BT-' . now()->format('Ymd') . '-' . str_pad((string) $paiement->id, 4, '0', STR_PAD_LEFT),
                ]);
            }

            return $res;
        });

        $this->logger->record(
            action: 'gerante_creation_reservation',
            module: 'reservations',
            description: "Reservation #{$reservation->id} creee en physique pour la cliente #{$reservation->client_id}",
            subject: $reservation,
            after: [
                'statut'        => $reservation->statut,
                'montant_total' => $reservation->montant_total,
                'source'        => 'physique',
            ],
            metadata: array_filter([
                'reservation_id'   => $reservation->id,
                'client_id'        => $reservation->client_id,
                'acompte_encaisse' => ($data['enregistrer_acompte'] ?? false) && $reservation->statut === 'acompte_paye',
                'actor_role'       => 'gerante',
            ]),
            request: $request,
        );

        return response()->json([
            'message' => 'Reservation creee.',
            'data'    => $reservation->load($this->relations()),
        ], 201);
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
     * @param array<string, mixed> $detailData
     * @return array<string, mixed>
     */
    private function computeDetail(array $detailData, int $order): array
    {
        $coiffure = Coiffure::query()
            ->with(['variantes', 'options'])
            ->where('actif', true)
            ->find($detailData['coiffure_id']);

        if (! $coiffure) {
            throw ValidationException::withMessages([
                'details' => 'Coiffure inactive ou introuvable.',
            ]);
        }

        $variante = $coiffure->variantes
            ->where('id', $detailData['variante_coiffure_id'])
            ->where('actif', true)
            ->first();

        if (! $variante) {
            throw ValidationException::withMessages([
                'details' => 'Variante inactive ou non associee a la coiffure.',
            ]);
        }

        $quantity  = (int) ($detailData['quantite'] ?? 1);
        $unitPrice = (float) $variante->prix;
        $lineTotal = $unitPrice * $quantity;

        return [
            'coiffure_id'          => $coiffure->id,
            'variante_coiffure_id' => $variante->id,
            'coiffure_nom'         => $coiffure->nom,
            'variante_nom'         => $variante->nom,
            'prix_unitaire'        => round($unitPrice, 2),
            'duree_minutes'        => (int) $variante->duree_minutes * $quantity,
            'quantite'             => $quantity,
            'option_ids'           => [],
            'options_snapshot'     => [],
            'montant_options'      => 0,
            'montant_total'        => round($lineTotal, 2),
            'ordre'                => $order,
        ];
    }

    private function ensureOpenDay(Carbon $startsAt): void
    {
        $days   = $this->settingValue('jours_fermeture', []);
        $closed = is_array($days) ? $days : [];
        $day    = match ((int) $startsAt->dayOfWeekIso) {
            1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi',
            5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche',
            default => 'lundi',
        };

        if (in_array($day, $closed, true)) {
            throw ValidationException::withMessages([
                'date_reservation' => 'Le salon est ferme ce jour-la.',
            ]);
        }
    }

    private function ensureWithinBusinessHours(Carbon $startsAt, Carbon $endsAt): void
    {
        $open  = $this->settingValue('heure_ouverture', '09:00');
        $close = $this->settingValue('heure_fermeture', '19:00');

        if ($startsAt->format('H:i') < $open || $endsAt->format('H:i') > $close) {
            throw ValidationException::withMessages([
                'heure_debut' => "La reservation doit rester entre {$open} et {$close}.",
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureSalonCapacity(array $data, ?Reservation $reservation): void
    {
        $dailyLimit = max(1, (int) $this->settingValue('limite_reservations_par_jour', 15));
        $slotLimit  = max(1, (int) $this->settingValue('limite_reservations_par_creneau', 3));

        $dailyCount = Reservation::query()
            ->whereDate('date_reservation', $data['date_reservation'])
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->when($reservation, fn ($q) => $q->where('id', '!=', $reservation->id))
            ->count();

        if ($dailyCount >= $dailyLimit) {
            throw ValidationException::withMessages([
                'date_reservation' => "Le quota de {$dailyLimit} reservation(s) pour cette journee est atteint.",
            ]);
        }

        $slotCount = Reservation::query()
            ->whereDate('date_reservation', $data['date_reservation'])
            ->whereTime('heure_debut', $data['heure_debut'])
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->when($reservation, fn ($q) => $q->where('id', '!=', $reservation->id))
            ->count();

        if ($slotCount >= $slotLimit) {
            throw ValidationException::withMessages([
                'heure_debut' => "Ce creneau est complet ({$slotLimit} reservation(s) a {$data['heure_debut']}).",
            ]);
        }
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = ParametreSysteme::query()->where('cle', $key)->first();

        return $setting?->valeur['value'] ?? $default;
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
