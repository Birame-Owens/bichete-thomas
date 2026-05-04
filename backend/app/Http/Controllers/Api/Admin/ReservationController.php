<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CodePromo;
use App\Models\Coiffure;
use App\Models\ParametreSysteme;
use App\Models\RegleFidelite;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const STATUSES = [
        'en_attente',
        'confirmee',
        'acompte_paye',
        'en_cours',
        'terminee',
        'annulee',
        'absence',
    ];

    private const BLOCKING_STATUSES = [
        'en_attente',
        'confirmee',
        'acompte_paye',
        'en_cours',
        'terminee',
    ];

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $reservations = Reservation::query()
            ->with($this->relations())
            ->when($request->filled('statut'), fn ($query) => $query->where('statut', $request->string('statut')->toString()))
            ->when($request->integer('client_id'), fn ($query, int $clientId) => $query->where('client_id', $clientId))
            ->when($request->integer('coiffeuse_id'), fn ($query, int $coiffeuseId) => $query->where('coiffeuse_id', $coiffeuseId))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('date_reservation', $request->date('date')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date_reservation', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date_reservation', '<=', $request->date('date_to')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('notes', 'ilike', "%{$search}%")
                        ->orWhereHas('client', function ($clientQuery) use ($search): void {
                            $clientQuery->where('nom', 'ilike', "%{$search}%")
                                ->orWhere('prenom', 'ilike', "%{$search}%")
                                ->orWhere('telephone', 'ilike', "%{$search}%")
                                ->orWhere('email', 'ilike', "%{$search}%");
                        });

                    if (ctype_digit($search)) {
                        $subQuery->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('date_reservation')
            ->orderByDesc('heure_debut')
            ->paginate($perPage);

        return response()->json(['data' => $reservations]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedReservationData($request);

        $reservation = DB::transaction(fn () => $this->persistReservation($data));

        return response()->json([
            'message' => 'Reservation creee.',
            'data' => $reservation,
        ], 201);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return response()->json([
            'data' => $reservation->load($this->relations()),
        ]);
    }

    public function update(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $this->validatedReservationData($request, $reservation);

        $reservation = DB::transaction(fn () => $this->persistReservation($data, $reservation));

        return response()->json([
            'message' => 'Reservation mise a jour.',
            'data' => $reservation,
        ]);
    }

    public function updateStatus(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(self::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $oldStatus = $reservation->statut;
        $oldClientId = $reservation->client_id;

        $reservation->fill([
            'statut' => $data['statut'],
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $reservation->notes,
        ]);
        $this->applyStatusTimestamps($reservation);

        DB::transaction(function () use ($reservation, $oldClientId, $oldStatus): void {
            $reservation->save();
            $this->syncClientCompletion($oldClientId, $oldStatus, $reservation->client_id, $reservation->statut, $reservation->fidelite_appliquee);
        });

        return response()->json([
            'message' => 'Statut reservation mis a jour.',
            'data' => $reservation->load($this->relations()),
        ]);
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        DB::transaction(function () use ($reservation): void {
            $this->syncPromoUsage($reservation->code_promo_id, null);
            $this->syncClientCompletion($reservation->client_id, $reservation->statut, null, null, false);
            $reservation->delete();
        });

        return response()->json(['message' => 'Reservation supprimee.']);
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'client.preferences',
            'coiffeuse',
            'codePromo',
            'regleFidelite',
            'details.coiffure',
            'details.variante',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReservationData(Request $request, ?Reservation $reservation = null): array
    {
        $data = $request->validate([
            'client_id' => [$reservation ? 'sometimes' : 'required', 'integer', 'exists:clients,id'],
            'coiffeuse_id' => ['nullable', 'integer', Rule::exists('coiffeuses', 'id')->where('actif', true)],
            'date_reservation' => [$reservation ? 'sometimes' : 'required', 'date'],
            'heure_debut' => [$reservation ? 'sometimes' : 'required', 'date_format:H:i'],
            'statut' => ['sometimes', Rule::in(self::STATUSES)],
            'source' => ['sometimes', Rule::in(['admin', 'en_ligne', 'whatsapp', 'telephone', 'physique'])],
            'code_promo_id' => ['nullable', 'integer', 'exists:codes_promo,id'],
            'regle_fidelite_id' => ['nullable', 'integer', 'exists:regles_fidelite,id'],
            'montant_acompte' => ['nullable', 'numeric', 'min:0'],
            'devise' => ['sometimes', Rule::in(['FCFA'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'details' => [$reservation ? 'sometimes' : 'required', 'array', 'min:1'],
            'details.*.coiffure_id' => ['required_with:details', 'integer', 'exists:coiffures,id'],
            'details.*.variante_coiffure_id' => ['required_with:details', 'integer', 'exists:variantes_coiffures,id'],
            'details.*.quantite' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'details.*.option_ids' => ['sometimes', 'array'],
            'details.*.option_ids.*' => ['integer', 'distinct', 'exists:options_coiffures,id'],
        ]);

        $clientId = $data['client_id'] ?? $reservation?->client_id;
        $client = Client::query()->find($clientId);

        if (! $client) {
            throw ValidationException::withMessages([
                'client_id' => 'Client introuvable.',
            ]);
        }

        if ($client->est_blackliste) {
            throw ValidationException::withMessages([
                'client_id' => 'Ce client est dans la liste noire.',
            ]);
        }

        if (! array_key_exists('details', $data) && $reservation) {
            $data['details'] = $reservation->details->map(fn ($detail): array => [
                'coiffure_id' => $detail->coiffure_id,
                'variante_coiffure_id' => $detail->variante_coiffure_id,
                'quantite' => $detail->quantite,
                'option_ids' => $detail->option_ids ?? [],
            ])->all();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistReservation(array $data, ?Reservation $reservation = null): Reservation
    {
        $oldStatus = $reservation?->statut;
        $oldClientId = $reservation?->client_id;
        $oldCodePromoId = $reservation?->code_promo_id;

        $current = [
            'client_id' => $data['client_id'] ?? $reservation?->client_id,
            'coiffeuse_id' => $data['coiffeuse_id'] ?? $reservation?->coiffeuse_id,
            'date_reservation' => $data['date_reservation'] ?? $reservation?->date_reservation?->toDateString(),
            'heure_debut' => $data['heure_debut'] ?? substr((string) $reservation?->heure_debut, 0, 5),
            'statut' => $data['statut'] ?? $reservation?->statut ?? 'en_attente',
            'source' => $data['source'] ?? $reservation?->source ?? 'admin',
            'code_promo_id' => array_key_exists('code_promo_id', $data) ? $data['code_promo_id'] : $reservation?->code_promo_id,
            'regle_fidelite_id' => array_key_exists('regle_fidelite_id', $data) ? $data['regle_fidelite_id'] : $reservation?->regle_fidelite_id,
            'montant_acompte' => array_key_exists('montant_acompte', $data) ? $data['montant_acompte'] : null,
            'devise' => $data['devise'] ?? $reservation?->devise ?? 'FCFA',
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $reservation?->notes,
        ];

        $computed = $this->computeReservation($current, $data['details'], $reservation);

        $reservation ??= new Reservation();
        $reservation->fill([
            ...$current,
            ...$computed['reservation'],
        ]);
        $this->applyStatusTimestamps($reservation);
        $reservation->save();

        $reservation->details()->delete();

        foreach ($computed['details'] as $detail) {
            $reservation->details()->create($detail);
        }

        $this->syncPromoUsage($oldCodePromoId, $reservation->code_promo_id);
        $this->syncClientCompletion($oldClientId, $oldStatus, $reservation->client_id, $reservation->statut, $reservation->fidelite_appliquee);

        return $reservation->load($this->relations());
    }

    /**
     * @param array<string, mixed> $reservationData
     * @param array<int, array<string, mixed>> $detailsData
     * @return array{reservation: array<string, mixed>, details: array<int, array<string, mixed>>}
     */
    private function computeReservation(array $reservationData, array $detailsData, ?Reservation $reservation = null): array
    {
        $details = [];
        $subtotal = 0.0;
        $duration = 0;

        foreach (array_values($detailsData) as $index => $detailData) {
            $line = $this->computeDetail($detailData, $index + 1);
            $details[] = $line;
            $subtotal += (float) $line['montant_total'];
            $duration += (int) $line['duree_minutes'];
        }

        if ($duration < 1 || $subtotal < 0) {
            throw ValidationException::withMessages([
                'details' => 'La reservation doit contenir au moins une prestation valide.',
            ]);
        }

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', sprintf('%s %s', $reservationData['date_reservation'], $reservationData['heure_debut']));
        $endsAt = $startsAt->copy()->addMinutes($duration);

        if (! $startsAt->isSameDay($endsAt)) {
            throw ValidationException::withMessages([
                'heure_debut' => 'La reservation doit se terminer le meme jour.',
            ]);
        }

        $this->ensureWithinBusinessHours($startsAt, $endsAt);
        $this->ensureNoPlanningConflict($reservationData, $endsAt->format('H:i'), $reservation);

        $promoDiscount = $this->computePromoDiscount($reservationData['code_promo_id'], $subtotal, $startsAt, $reservation);
        $loyaltyDiscount = $this->computeLoyaltyDiscount($reservationData['regle_fidelite_id'], $reservationData['client_id'], $subtotal);
        $reduction = min($subtotal, $promoDiscount + $loyaltyDiscount);
        $total = max($subtotal - $reduction, 0);
        $deposit = array_key_exists('montant_acompte', $reservationData) && $reservationData['montant_acompte'] !== null
            ? (float) $reservationData['montant_acompte']
            : $this->defaultDeposit($total);
        $deposit = min(max($deposit, 0), $total);

        return [
            'reservation' => [
                'heure_fin' => $endsAt->format('H:i'),
                'duree_totale_minutes' => $duration,
                'montant_total' => round($total, 2),
                'montant_reduction' => round($reduction, 2),
                'montant_acompte' => round($deposit, 2),
                'montant_restant' => round(max($total - $deposit, 0), 2),
                'fidelite_appliquee' => $loyaltyDiscount > 0,
            ],
            'details' => $details,
        ];
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

        $quantity = (int) ($detailData['quantite'] ?? 1);
        $optionIds = collect($detailData['option_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $options = $coiffure->options
            ->where('actif', true)
            ->whereIn('id', $optionIds);

        if ($options->count() !== $optionIds->count()) {
            throw ValidationException::withMessages([
                'details' => 'Une option selectionnee est inactive ou non associee a la coiffure.',
            ]);
        }

        $optionAmount = (float) $options->sum(fn ($option): float => (float) $option->prix);
        $unitPrice = (float) $variante->prix;
        $lineTotal = ($unitPrice + $optionAmount) * $quantity;
        $lineDuration = (int) $variante->duree_minutes * $quantity;

        return [
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $variante->id,
            'coiffure_nom' => $coiffure->nom,
            'variante_nom' => $variante->nom,
            'prix_unitaire' => round($unitPrice, 2),
            'duree_minutes' => $lineDuration,
            'quantite' => $quantity,
            'option_ids' => $optionIds->all(),
            'options_snapshot' => $options->map(fn ($option): array => [
                'id' => $option->id,
                'nom' => $option->nom,
                'prix' => (float) $option->prix,
            ])->values()->all(),
            'montant_options' => round($optionAmount * $quantity, 2),
            'montant_total' => round($lineTotal, 2),
            'ordre' => $order,
        ];
    }

    private function ensureWithinBusinessHours(Carbon $startsAt, Carbon $endsAt): void
    {
        $open = $this->settingValue('heure_ouverture', '09:00');
        $close = $this->settingValue('heure_fermeture', '19:00');

        if ($startsAt->format('H:i') < $open || $endsAt->format('H:i') > $close) {
            throw ValidationException::withMessages([
                'heure_debut' => "La reservation doit rester entre {$open} et {$close}.",
            ]);
        }
    }

    /**
     * @param array<string, mixed> $reservationData
     */
    private function ensureNoPlanningConflict(array $reservationData, string $endTime, ?Reservation $reservation): void
    {
        if (! $reservationData['coiffeuse_id'] || ! in_array($reservationData['statut'], self::BLOCKING_STATUSES, true)) {
            return;
        }

        $exists = Reservation::query()
            ->where('coiffeuse_id', $reservationData['coiffeuse_id'])
            ->whereDate('date_reservation', $reservationData['date_reservation'])
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->when($reservation, fn ($query) => $query->where('id', '!=', $reservation->id))
            ->where('heure_debut', '<', $endTime)
            ->where('heure_fin', '>', $reservationData['heure_debut'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'coiffeuse_id' => 'Cette coiffeuse a deja une reservation sur ce creneau.',
            ]);
        }
    }

    private function computePromoDiscount(?int $codePromoId, float $subtotal, Carbon $startsAt, ?Reservation $reservation): float
    {
        if (! $codePromoId) {
            return 0.0;
        }

        $promo = CodePromo::query()->find($codePromoId);

        if (! $promo || ! $promo->actif) {
            throw ValidationException::withMessages([
                'code_promo_id' => 'Code promo inactif ou introuvable.',
            ]);
        }

        if ($promo->date_debut && $startsAt->lt($promo->date_debut)) {
            throw ValidationException::withMessages([
                'code_promo_id' => 'Ce code promo n est pas encore disponible.',
            ]);
        }

        if ($promo->date_fin && $startsAt->gt($promo->date_fin)) {
            throw ValidationException::withMessages([
                'code_promo_id' => 'Ce code promo est expire.',
            ]);
        }

        $samePromo = $reservation?->code_promo_id === $promo->id;
        if (! $samePromo && $promo->limite_utilisation !== null && $promo->nombre_utilisations >= $promo->limite_utilisation) {
            throw ValidationException::withMessages([
                'code_promo_id' => 'Ce code promo a atteint sa limite d utilisation.',
            ]);
        }

        return $this->discountAmount($promo->type_reduction, (float) $promo->valeur, $subtotal);
    }

    private function computeLoyaltyDiscount(?int $ruleId, int $clientId, float $subtotal): float
    {
        if (! $ruleId) {
            return 0.0;
        }

        $rule = RegleFidelite::query()->find($ruleId);
        $client = Client::query()->find($clientId);

        if (! $rule || ! $rule->actif || ! $client) {
            throw ValidationException::withMessages([
                'regle_fidelite_id' => 'Regle fidelite inactive ou introuvable.',
            ]);
        }

        if (! $client->fidelite_disponible && $client->nombre_reservations_terminees < $rule->nombre_reservations_requis) {
            throw ValidationException::withMessages([
                'regle_fidelite_id' => 'Ce client n a pas encore atteint cette recompense.',
            ]);
        }

        return $this->discountAmount($rule->type_recompense, (float) $rule->valeur_recompense, $subtotal);
    }

    private function discountAmount(string $type, float $value, float $subtotal): float
    {
        if ($type === 'pourcentage') {
            return min($subtotal, $subtotal * ($value / 100));
        }

        return min($subtotal, $value);
    }

    private function defaultDeposit(float $total): float
    {
        $percent = (float) $this->settingValue('pourcentage_acompte', 0);
        $fallback = (float) $this->settingValue('montant_acompte_defaut', 0);

        if ($percent > 0) {
            return round($total * ($percent / 100), 2);
        }

        return min($fallback, $total);
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        $setting = ParametreSysteme::query()->where('cle', $key)->first();

        return $setting?->valeur['value'] ?? $default;
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

    private function syncPromoUsage(?int $oldCodePromoId, ?int $newCodePromoId): void
    {
        if ($oldCodePromoId === $newCodePromoId) {
            return;
        }

        if ($oldCodePromoId) {
            CodePromo::query()
                ->whereKey($oldCodePromoId)
                ->where('nombre_utilisations', '>', 0)
                ->decrement('nombre_utilisations');
        }

        if ($newCodePromoId) {
            CodePromo::query()->whereKey($newCodePromoId)->increment('nombre_utilisations');
        }
    }

    private function syncClientCompletion(?int $oldClientId, ?string $oldStatus, ?int $newClientId, ?string $newStatus, bool $loyaltyConsumed): void
    {
        if ($oldClientId && $oldStatus === 'terminee') {
            $this->decrementClientCompleted($oldClientId);
        }

        if ($newClientId && $newStatus === 'terminee') {
            $this->incrementClientCompleted($newClientId, $loyaltyConsumed);
        }
    }

    private function incrementClientCompleted(int $clientId, bool $loyaltyConsumed): void
    {
        $client = Client::query()->find($clientId);

        if (! $client) {
            return;
        }

        $client->increment('nombre_reservations_terminees');
        $client->refresh();
        $this->refreshClientLoyalty($client, $loyaltyConsumed);
    }

    private function decrementClientCompleted(int $clientId): void
    {
        $client = Client::query()->find($clientId);

        if (! $client) {
            return;
        }

        $client->update([
            'nombre_reservations_terminees' => max($client->nombre_reservations_terminees - 1, 0),
        ]);
        $client->refresh();
        $this->refreshClientLoyalty($client, false);
    }

    private function refreshClientLoyalty(Client $client, bool $loyaltyConsumed): void
    {
        if ($loyaltyConsumed) {
            $client->update(['fidelite_disponible' => false]);
            return;
        }

        $minimum = RegleFidelite::query()
            ->where('actif', true)
            ->min('nombre_reservations_requis');

        if ($minimum === null) {
            return;
        }

        $client->update([
            'fidelite_disponible' => $client->nombre_reservations_terminees >= (int) $minimum,
        ]);
    }
}
