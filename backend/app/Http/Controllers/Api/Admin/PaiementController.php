<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caisse;
use App\Models\MouvementCaisse;
use App\Models\Paiement;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaiementController extends Controller
{
    private const TYPES = ['acompte', 'solde', 'complet', 'remboursement', 'ajustement'];
    private const INCOMING_TYPES = ['acompte', 'solde', 'complet', 'ajustement'];
    private const METHODS = ['especes', 'wave', 'orange_money', 'carte_bancaire', 'virement', 'autre'];
    private const STATUSES = ['en_attente', 'valide', 'annule', 'rembourse'];

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $paiements = $this->filteredQuery($request)
            ->with($this->relations())
            ->latest('date_paiement')
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $paiements,
            'meta' => $this->summary($request),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedPaymentData($request);

        $paiement = DB::transaction(fn (): Paiement => $this->persistPayment($data));

        return response()->json([
            'message' => 'Paiement enregistre.',
            'data' => $paiement,
            'receipt' => $this->receiptData($paiement),
        ], 201);
    }

    public function show(Paiement $paiement): JsonResponse
    {
        $paiement->load($this->relations());

        return response()->json([
            'data' => $paiement,
            'receipt' => $this->receiptData($paiement),
        ]);
    }

    public function update(Request $request, Paiement $paiement): JsonResponse
    {
        $data = $this->validatedPaymentData($request, $paiement);

        $paiement = DB::transaction(fn (): Paiement => $this->persistPayment($data, $paiement));

        return response()->json([
            'message' => 'Paiement mis a jour.',
            'data' => $paiement,
            'receipt' => $this->receiptData($paiement),
        ]);
    }

    public function cancel(Request $request, Paiement $paiement): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $reservationId = $paiement->reservation_id;

        DB::transaction(function () use ($paiement, $data, $reservationId): void {
            $this->ensureCashMovementCanChange($paiement);
            $this->removeCashMovement($paiement);
            $paiement->forceFill([
                'statut' => 'annule',
                'notes' => $data['notes'] ?? $paiement->notes,
            ])->save();
            $this->syncReservationPaymentState($reservationId);
        });

        return response()->json([
            'message' => 'Paiement annule.',
            'data' => $paiement->fresh($this->relations()),
        ]);
    }

    public function markReceiptSent(Paiement $paiement): JsonResponse
    {
        $paiement->update([
            'recu_envoye' => true,
            'recu_envoye_at' => now(),
        ]);

        return response()->json([
            'message' => 'Recu marque comme envoye.',
            'data' => $paiement->fresh($this->relations()),
        ]);
    }

    public function receipt(Paiement $paiement): JsonResponse
    {
        return response()->json([
            'data' => $this->receiptData($paiement->load($this->relations())),
        ]);
    }

    public function destroy(Paiement $paiement): JsonResponse
    {
        $reservationId = $paiement->reservation_id;

        DB::transaction(function () use ($paiement, $reservationId): void {
            $this->ensureCashMovementCanChange($paiement);
            $this->removeCashMovement($paiement);
            $paiement->delete();
            $this->syncReservationPaymentState($reservationId);
        });

        return response()->json(['message' => 'Paiement supprime.']);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'client',
            'reservation.client',
            'reservation.details',
            'caisse',
            'mouvementCaisse.caisse',
        ];
    }

    private function filteredQuery(Request $request): Builder
    {
        return Paiement::query()
            ->when($request->filled('statut'), fn (Builder $query) => $query->where('statut', $request->string('statut')->toString()))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('mode_paiement'), fn (Builder $query) => $query->where('mode_paiement', $request->string('mode_paiement')->toString()))
            ->when($request->integer('reservation_id'), fn (Builder $query, int $reservationId) => $query->where('reservation_id', $reservationId))
            ->when($request->integer('client_id'), fn (Builder $query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('date_paiement', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('date_paiement', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function (Builder $subQuery) use ($search): void {
                    $subQuery->where('numero_recu', 'ilike', "%{$search}%")
                        ->orWhere('reference', 'ilike', "%{$search}%")
                        ->orWhereHas('client', function (Builder $clientQuery) use ($search): void {
                            $clientQuery->where('nom', 'ilike', "%{$search}%")
                                ->orWhere('prenom', 'ilike', "%{$search}%")
                                ->orWhere('telephone', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('reservation.client', function (Builder $clientQuery) use ($search): void {
                            $clientQuery->where('nom', 'ilike', "%{$search}%")
                                ->orWhere('prenom', 'ilike', "%{$search}%")
                                ->orWhere('telephone', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('reservation.details', function (Builder $detailQuery) use ($search): void {
                            $detailQuery->where('coiffure_nom', 'ilike', "%{$search}%")
                                ->orWhere('variante_nom', 'ilike', "%{$search}%");
                        });

                    if (ctype_digit($search)) {
                        $subQuery->orWhere('id', (int) $search)
                            ->orWhere('reservation_id', (int) $search);
                    }
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Request $request): array
    {
        $base = $this->filteredQuery($request);
        $valid = (clone $base)->where('statut', 'valide');

        return [
            'total_paye' => (float) (clone $valid)->whereIn('type', self::INCOMING_TYPES)->sum('montant'),
            'total_acomptes' => (float) (clone $valid)->where('type', 'acompte')->sum('montant'),
            'total_soldes' => (float) (clone $valid)->whereIn('type', ['solde', 'complet'])->sum('montant'),
            'total_attente' => (int) (clone $base)->where('statut', 'en_attente')->count(),
            'total_annule' => (float) (clone $base)->where('statut', 'annule')->sum('montant'),
            'nombre_paiements' => (int) (clone $base)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPaymentData(Request $request, ?Paiement $paiement = null): array
    {
        $validated = $request->validate([
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'type' => [$paiement ? 'sometimes' : 'required', Rule::in(self::TYPES)],
            'mode_paiement' => [$paiement ? 'sometimes' : 'required', Rule::in(self::METHODS)],
            'montant' => [$paiement ? 'sometimes' : 'required', 'numeric', 'min:1'],
            'devise' => ['sometimes', Rule::in(['FCFA'])],
            'statut' => ['sometimes', Rule::in(self::STATUSES)],
            'date_paiement' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'recu_envoye' => ['sometimes', 'boolean'],
        ]);

        $data = [
            'reservation_id' => array_key_exists('reservation_id', $validated) ? $validated['reservation_id'] : $paiement?->reservation_id,
            'client_id' => array_key_exists('client_id', $validated) ? $validated['client_id'] : $paiement?->client_id,
            'type' => $validated['type'] ?? $paiement?->type ?? 'acompte',
            'mode_paiement' => $validated['mode_paiement'] ?? $paiement?->mode_paiement ?? 'especes',
            'montant' => array_key_exists('montant', $validated) ? (float) $validated['montant'] : (float) ($paiement?->montant ?? 0),
            'devise' => $validated['devise'] ?? $paiement?->devise ?? 'FCFA',
            'statut' => $validated['statut'] ?? $paiement?->statut ?? 'valide',
            'date_paiement' => $validated['date_paiement'] ?? $paiement?->date_paiement?->toDateTimeString() ?? now()->toDateTimeString(),
            'reference' => array_key_exists('reference', $validated) ? $validated['reference'] : $paiement?->reference,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $paiement?->notes,
            'recu_envoye' => array_key_exists('recu_envoye', $validated) ? (bool) $validated['recu_envoye'] : (bool) ($paiement?->recu_envoye ?? false),
        ];

        $reservation = $data['reservation_id'] ? Reservation::query()->find((int) $data['reservation_id']) : null;

        if (! $reservation && ! $data['client_id']) {
            throw ValidationException::withMessages([
                'client_id' => 'Selectionnez une reservation ou un client.',
            ]);
        }

        if ($reservation?->client_id) {
            if ($data['client_id'] && (int) $data['client_id'] !== (int) $reservation->client_id) {
                throw ValidationException::withMessages([
                    'client_id' => 'Le client doit correspondre a la reservation selectionnee.',
                ]);
            }

            $data['client_id'] = $reservation->client_id;
        }

        if ($reservation && in_array($reservation->statut, ['annulee', 'absence'], true) && in_array($data['type'], self::INCOMING_TYPES, true)) {
            throw ValidationException::withMessages([
                'reservation_id' => 'Impossible d encaisser une reservation annulee ou marquee absente.',
            ]);
        }

        $this->ensureReservationAmountIsCoherent($data, $reservation, $paiement);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureReservationAmountIsCoherent(array $data, ?Reservation $reservation, ?Paiement $paiement): void
    {
        if (! $reservation) {
            return;
        }

        $paid = $this->reservationPaidAmount($reservation->id, $paiement?->id);
        $remaining = max((float) $reservation->montant_total - $paid, 0);
        $amount = (float) $data['montant'];

        if (in_array($data['type'], self::INCOMING_TYPES, true) && $amount > $remaining + 0.01) {
            throw ValidationException::withMessages([
                'montant' => sprintf('Le montant depasse le reste du: %s FCFA.', number_format($remaining, 0, ',', ' ')),
            ]);
        }

        if ($data['type'] === 'remboursement' && $amount > $paid + 0.01) {
            throw ValidationException::withMessages([
                'montant' => sprintf('Le remboursement depasse le montant deja encaisse: %s FCFA.', number_format($paid, 0, ',', ' ')),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistPayment(array $data, ?Paiement $paiement = null): Paiement
    {
        $oldReservationId = $paiement?->reservation_id;

        if ($paiement) {
            $this->ensureCashMovementCanChange($paiement);
        }

        $paymentDate = Carbon::parse($data['date_paiement']);
        $paiement ??= new Paiement();
        $isNew = ! $paiement->exists;

        $paiement->fill([
            ...$data,
            'date_paiement' => $paymentDate,
            'numero_recu' => $isNew ? 'TEMP-' . Str::uuid()->toString() : $paiement->numero_recu,
            'recu_envoye_at' => $data['recu_envoye'] ? ($paiement->recu_envoye_at ?? now()) : null,
        ]);
        $paiement->save();

        if ($isNew) {
            $paiement->forceFill([
                'numero_recu' => $this->receiptNumberForPayment($paymentDate, $paiement->id),
            ])->save();
        }

        $this->syncCashMovement($paiement);
        $this->syncReservationPaymentState($oldReservationId);
        $this->syncReservationPaymentState($paiement->reservation_id);

        return $paiement->fresh($this->relations());
    }

    private function receiptNumberForPayment(Carbon $date, int $paymentId): string
    {
        return 'BT-' . $date->format('Ymd') . '-' . str_pad((string) $paymentId, 4, '0', STR_PAD_LEFT);
    }

    private function syncCashMovement(Paiement $paiement): void
    {
        if ($paiement->statut === 'valide' && in_array($paiement->type, self::INCOMING_TYPES, true)) {
            $caisse = $this->matchingOpenCaisse($paiement);

            if (! $caisse) {
                $this->removeCashMovement($paiement);
                return;
            }

            $movement = $paiement->mouvementCaisse;

            if ($movement) {
                $this->ensureCashMovementCanChange($paiement);
                $movement->update([
                    'caisse_id' => $caisse->id,
                    'type' => 'entree',
                    'montant' => $paiement->montant,
                    'description' => "Paiement {$paiement->numero_recu}",
                    'source' => "paiement:{$paiement->mode_paiement}",
                    'reference' => $paiement->reference ?: $paiement->numero_recu,
                    'date_mouvement' => $paiement->date_paiement,
                ]);
            } else {
                $movement = MouvementCaisse::query()->create([
                    'caisse_id' => $caisse->id,
                    'type' => 'entree',
                    'montant' => $paiement->montant,
                    'description' => "Paiement {$paiement->numero_recu}",
                    'source' => "paiement:{$paiement->mode_paiement}",
                    'reference' => $paiement->reference ?: $paiement->numero_recu,
                    'date_mouvement' => $paiement->date_paiement,
                ]);
            }

            $paiement->forceFill([
                'caisse_id' => $caisse->id,
                'mouvement_caisse_id' => $movement->id,
            ])->save();

            return;
        }

        $this->removeCashMovement($paiement);
    }

    private function matchingOpenCaisse(Paiement $paiement): ?Caisse
    {
        if ($paiement->caisse_id) {
            $caisse = Caisse::query()->whereKey($paiement->caisse_id)->first();

            if ($caisse?->statut === 'ouverte') {
                return $caisse;
            }
        }

        return Caisse::query()
            ->whereDate('date', $paiement->date_paiement?->toDateString() ?? now()->toDateString())
            ->where('statut', 'ouverte')
            ->first();
    }

    private function ensureCashMovementCanChange(Paiement $paiement): void
    {
        $movement = $paiement->mouvementCaisse;

        if (! $movement) {
            return;
        }

        $movement->loadMissing('caisse');

        if ($movement->caisse?->statut === 'fermee') {
            throw ValidationException::withMessages([
                'caisse_id' => 'Impossible de modifier ce paiement: la caisse associee est fermee.',
            ]);
        }
    }

    private function removeCashMovement(Paiement $paiement): void
    {
        if ($paiement->mouvementCaisse) {
            $this->ensureCashMovementCanChange($paiement);
            $paiement->mouvementCaisse->delete();
        }

        if ($paiement->mouvement_caisse_id || $paiement->caisse_id) {
            $paiement->forceFill([
                'mouvement_caisse_id' => null,
                'caisse_id' => null,
            ])->save();
        }
    }

    private function syncReservationPaymentState(?int $reservationId): void
    {
        if (! $reservationId) {
            return;
        }

        $reservation = Reservation::query()->find($reservationId);

        if (! $reservation) {
            return;
        }

        $paid = $this->reservationPaidAmount($reservation->id);
        $remaining = max((float) $reservation->montant_total - $paid, 0);
        $updates = [
            'montant_restant' => round($remaining, 2),
        ];

        if ($paid > 0 && in_array($reservation->statut, ['en_attente', 'confirmee'], true)) {
            $updates['statut'] = 'acompte_paye';
        }

        if ($paid <= 0 && $reservation->statut === 'acompte_paye') {
            $updates['statut'] = 'confirmee';
        }

        $reservation->forceFill($updates)->save();
    }

    private function reservationPaidAmount(int $reservationId, ?int $exceptPaymentId = null): float
    {
        $incoming = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->where('statut', 'valide')
            ->whereIn('type', self::INCOMING_TYPES)
            ->when($exceptPaymentId, fn (Builder $query) => $query->whereKeyNot($exceptPaymentId))
            ->sum('montant');

        $refunds = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('statut', ['valide', 'rembourse'])
            ->where('type', 'remboursement')
            ->when($exceptPaymentId, fn (Builder $query) => $query->whereKeyNot($exceptPaymentId))
            ->sum('montant');

        return max((float) $incoming - (float) $refunds, 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptData(Paiement $paiement): array
    {
        $paiement->loadMissing($this->relations());

        $reservation = $paiement->reservation;
        $client = $paiement->client ?? $reservation?->client;
        $reservationPaid = $reservation ? $this->reservationPaidAmount($reservation->id) : (float) $paiement->montant;
        $reservationTotal = $reservation ? (float) $reservation->montant_total : (float) $paiement->montant;
        $remaining = max($reservationTotal - $reservationPaid, 0);

        return [
            'salon' => [
                'nom' => 'Bichette Thomas',
                'description' => 'Salon de Coiffure',
                'telephone_whatsapp' => $this->settingValue('telephone_whatsapp', null),
                'devise' => $paiement->devise,
            ],
            'numero_recu' => $paiement->numero_recu,
            'date' => $paiement->date_paiement?->toISOString(),
            'client' => [
                'id' => $client?->id,
                'nom' => $client ? trim("{$client->prenom} {$client->nom}") : 'Client non renseigne',
                'telephone' => $client?->telephone,
                'email' => $client?->email,
            ],
            'reservation' => $reservation ? [
                'id' => $reservation->id,
                'date_reservation' => $reservation->date_reservation?->toDateString(),
                'heure_debut' => substr((string) $reservation->heure_debut, 0, 5),
                'statut' => $reservation->statut,
                'services' => $reservation->details->map(fn ($detail): array => [
                    'coiffure' => $detail->coiffure_nom,
                    'variante' => $detail->variante_nom,
                    'quantite' => $detail->quantite,
                    'montant' => (float) $detail->montant_total,
                ])->values(),
            ] : null,
            'paiement' => [
                'id' => $paiement->id,
                'type' => $paiement->type,
                'mode_paiement' => $paiement->mode_paiement,
                'montant' => (float) $paiement->montant,
                'devise' => $paiement->devise,
                'statut' => $paiement->statut,
                'reference' => $paiement->reference,
                'notes' => $paiement->notes,
                'recu_envoye' => $paiement->recu_envoye,
                'recu_envoye_at' => $paiement->recu_envoye_at?->toISOString(),
            ],
            'totaux' => [
                'montant_reservation' => $reservationTotal,
                'montant_deja_paye' => $reservationPaid,
                'reste_a_payer' => $remaining,
            ],
        ];
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = ParametreSysteme::query()->where('cle', $key)->first();

        return $setting?->valeur['value'] ?? $default;
    }
}
