<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CodePromo;
use App\Models\Coiffure;
use App\Models\Paiement;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const BLOCKING_STATUSES = [
        'en_attente',
        'confirmee',
        'acompte_paye',
        'en_cours',
        'terminee',
    ];
    private const ONLINE_PAYMENT_METHODS = ['wave', 'orange_money', 'carte_bancaire'];

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client' => ['required', 'array'],
            'client.nom' => ['required', 'string', 'max:255'],
            'client.prenom' => ['required', 'string', 'max:255'],
            'client.telephone' => ['required', 'string', 'max:50'],
            'client.email' => ['nullable', 'email', 'max:255'],
            'coiffure_id' => ['required', 'integer', 'exists:coiffures,id'],
            'variante_coiffure_id' => ['required', 'integer', 'exists:variantes_coiffures,id'],
            'option_ids' => ['sometimes', 'array'],
            'option_ids.*' => ['integer', 'distinct', 'exists:options_coiffures,id'],
            'date_reservation' => ['required', 'date', 'after_or_equal:today'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'code_promo' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mode_paiement' => ['required', Rule::in(self::ONLINE_PAYMENT_METHODS)],
            'reference_paiement' => ['nullable', 'string', 'max:255'],
            'success_url' => ['nullable', 'url', 'max:2048'],
            'cancel_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $result = DB::transaction(fn (): array => $this->createReservation($data));

        return response()->json([
            'message' => $result['message'],
            'data' => $result['reservation'],
            'payment' => $result['payment'],
            'checkout_url' => $result['checkout_url'],
            'requires_redirect' => $result['requires_redirect'],
        ], 201);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function createReservation(array $data): array
    {
        $this->ensurePaymentReference($data);

        $client = $this->findOrCreateClient($data['client']);
        $coiffure = Coiffure::query()
            ->with(['variantes', 'options'])
            ->where('actif', true)
            ->find($data['coiffure_id']);

        if (! $coiffure) {
            throw ValidationException::withMessages([
                'coiffure_id' => 'Cette coiffure n est plus disponible.',
            ]);
        }

        $variante = $coiffure->variantes
            ->where('id', $data['variante_coiffure_id'])
            ->where('actif', true)
            ->first();

        if (! $variante) {
            throw ValidationException::withMessages([
                'variante_coiffure_id' => 'Cette variante n est plus disponible.',
            ]);
        }

        $optionIds = collect($data['option_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $options = $coiffure->options
            ->where('actif', true)
            ->whereIn('id', $optionIds);

        if ($options->count() !== $optionIds->count()) {
            throw ValidationException::withMessages([
                'option_ids' => 'Une option selectionnee n est plus disponible.',
            ]);
        }

        $startsAt = Carbon::createFromFormat('Y-m-d H:i', "{$data['date_reservation']} {$data['heure_debut']}");
        $duration = (int) $variante->duree_minutes;
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $this->ensureNotPastSlot($startsAt);
        $this->ensureWithinBusinessHours($startsAt, $endsAt);
        $this->ensureSalonCapacity($startsAt->toDateString(), $startsAt->format('H:i'));

        $subtotal = (float) $variante->prix + (float) $options->sum(fn ($option): float => (float) $option->prix);
        $promo = $this->findPromo($data['code_promo'] ?? null, $subtotal, $startsAt);
        $discount = $promo ? $this->discountAmount($promo->type_reduction, (float) $promo->valeur, $subtotal) : 0.0;
        $total = max($subtotal - $discount, 0);
        $deposit = $this->defaultDeposit($total);

        $reservation = Reservation::query()->create([
            'client_id' => $client->id,
            'coiffeuse_id' => null,
            'code_promo_id' => $promo?->id,
            'date_reservation' => $startsAt->toDateString(),
            'heure_debut' => $startsAt->format('H:i'),
            'heure_fin' => $endsAt->format('H:i'),
            'duree_totale_minutes' => $duration,
            'statut' => 'en_attente',
            'source' => 'en_ligne',
            'montant_total' => round($total, 2),
            'montant_reduction' => round($discount, 2),
            'montant_acompte' => round($deposit, 2),
            'montant_restant' => round(max($total - $deposit, 0), 2),
            'devise' => 'FCFA',
            'notes' => $data['notes'] ?? null,
        ]);

        $reservation->details()->create([
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $variante->id,
            'coiffure_nom' => $coiffure->nom,
            'variante_nom' => $variante->nom,
            'prix_unitaire' => round((float) $variante->prix, 2),
            'duree_minutes' => $duration,
            'quantite' => 1,
            'option_ids' => $optionIds->all(),
            'options_snapshot' => $options->map(fn ($option): array => [
                'id' => $option->id,
                'nom' => $option->nom,
                'prix' => (float) $option->prix,
            ])->values()->all(),
            'montant_options' => round((float) $options->sum(fn ($option): float => (float) $option->prix), 2),
            'montant_total' => round($subtotal, 2),
            'ordre' => 1,
        ]);

        if ($promo) {
            $promo->increment('nombre_utilisations');
        }

        $payment = $this->createPendingPayment(
            $reservation,
            $client,
            (string) $data['mode_paiement'],
            $data['reference_paiement'] ?? null,
            $deposit > 0 ? $deposit : $total,
            $total
        );
        $checkoutUrl = null;

        if ($payment->mode_paiement === 'carte_bancaire') {
            $checkoutUrl = $this->createStripeCheckoutSession($payment, $reservation, $data);
        }

        return [
            'message' => $checkoutUrl
                ? 'Reservation creee. Continuez vers Stripe pour payer l acompte.'
                : 'Paiement enregistre. Le salon validera la transaction avant confirmation.',
            'reservation' => $reservation->load(['client', 'details', 'codePromo']),
            'payment' => $payment->fresh(['reservation', 'client']),
            'checkout_url' => $checkoutUrl,
            'requires_redirect' => $checkoutUrl !== null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensurePaymentReference(array $data): void
    {
        if (in_array($data['mode_paiement'] ?? null, ['wave', 'orange_money'], true)
            && trim((string) ($data['reference_paiement'] ?? '')) === ''
        ) {
            throw ValidationException::withMessages([
                'reference_paiement' => 'Renseignez la reference de transaction Wave ou Orange Money.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findOrCreateClient(array $data): Client
    {
        $telephone = trim((string) $data['telephone']);
        $client = Client::query()->where('telephone', $telephone)->first();

        if ($client) {
            if ($client->est_blackliste) {
                throw ValidationException::withMessages([
                    'client.telephone' => 'Ce telephone ne peut pas effectuer de reservation en ligne.',
                ]);
            }

            return $client;
        }

        $client = Client::query()->create([
            'nom' => trim((string) $data['nom']),
            'prenom' => trim((string) $data['prenom']),
            'telephone' => $telephone,
            'email' => $data['email'] ?? null,
            'source' => 'en_ligne',
        ]);

        $client->preferences()->create([
            'notifications_whatsapp' => true,
            'notifications_promos' => true,
        ]);

        return $client;
    }

    private function ensureWithinBusinessHours(Carbon $startsAt, Carbon $endsAt): void
    {
        $open = (string) $this->settingValue('heure_ouverture', '09:00');
        $close = (string) $this->settingValue('heure_fermeture', '19:00');

        if ($startsAt->format('H:i') < $open || $endsAt->format('H:i') > $close) {
            throw ValidationException::withMessages([
                'heure_debut' => "Choisissez une heure entre {$open} et {$close}.",
            ]);
        }
    }

    private function ensureNotPastSlot(Carbon $startsAt): void
    {
        if ($startsAt->lt(now())) {
            throw ValidationException::withMessages([
                'heure_debut' => 'Choisissez un creneau a venir.',
            ]);
        }
    }

    private function ensureSalonCapacity(string $date, string $hour): void
    {
        $dailyLimit = max(1, (int) $this->settingValue('limite_reservations_par_jour', 15));
        $slotLimit = max(1, (int) $this->settingValue('limite_reservations_par_creneau', 3));
        $dailyCount = Reservation::query()
            ->whereDate('date_reservation', $date)
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->count();

        if ($dailyCount >= $dailyLimit) {
            throw ValidationException::withMessages([
                'date_reservation' => 'Cette journee est deja complete.',
            ]);
        }

        $slotCount = Reservation::query()
            ->whereDate('date_reservation', $date)
            ->whereTime('heure_debut', $hour)
            ->whereIn('statut', self::BLOCKING_STATUSES)
            ->count();

        if ($slotCount >= $slotLimit) {
            throw ValidationException::withMessages([
                'heure_debut' => 'Ce creneau est deja complet.',
            ]);
        }
    }

    private function createPendingPayment(
        Reservation $reservation,
        Client $client,
        string $method,
        ?string $reference,
        float $amount,
        float $reservationTotal
    ): Paiement {
        $amount = max($amount, 1);
        $paymentDate = now();
        $payment = Paiement::query()->create([
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'numero_recu' => 'TEMP-' . Str::uuid()->toString(),
            'type' => $amount >= $reservationTotal ? 'complet' : 'acompte',
            'mode_paiement' => $method,
            'montant' => round($amount, 2),
            'devise' => 'FCFA',
            'statut' => 'en_attente',
            'date_paiement' => $paymentDate,
            'reference' => $reference ? trim($reference) : null,
            'notes' => $method === 'carte_bancaire'
                ? 'Paiement carte bancaire initie depuis l espace client via Stripe.'
                : 'Paiement mobile money initie depuis l espace client, a valider dans l administration.',
            'recu_envoye' => false,
        ]);

        $payment->forceFill([
            'numero_recu' => $this->receiptNumberForPayment($paymentDate, $payment->id),
        ])->save();

        return $payment;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createStripeCheckoutSession(Paiement $payment, Reservation $reservation, array $data): string
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'Le paiement par carte bancaire n est pas encore configure.',
            ]);
        }

        $successUrl = $data['success_url']
            ?? config('services.stripe.success_url')
            ?? config('app.url') . '/client?paiement=stripe_success&stripe_session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $data['cancel_url']
            ?? config('services.stripe.cancel_url')
            ?? config('app.url') . '/client?paiement=stripe_cancel';

        $payload = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => (string) $reservation->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => config('services.stripe.currency', 'xof'),
                    'unit_amount' => (int) round((float) $payment->montant),
                    'product_data' => [
                        'name' => "Acompte reservation #{$reservation->id}",
                        'description' => 'Bichette Thomas - Salon de Coiffure',
                    ],
                ],
            ]],
            'metadata' => [
                'reservation_id' => (string) $reservation->id,
                'paiement_id' => (string) $payment->id,
                'numero_recu' => $payment->numero_recu,
            ],
        ];

        if ($reservation->client?->email) {
            $payload['customer_email'] = $reservation->client->email;
        }

        $response = Http::asForm()
            ->withToken((string) $secret)
            ->post('https://api.stripe.com/v1/checkout/sessions', $payload);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'Stripe n a pas pu initialiser le paiement carte.',
            ]);
        }

        $session = $response->json();
        $checkoutUrl = $session['url'] ?? null;
        $sessionId = $session['id'] ?? null;

        if (! $checkoutUrl || ! $sessionId) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'Stripe n a pas retourne de lien de paiement.',
            ]);
        }

        $payment->forceFill([
            'reference' => $sessionId,
        ])->save();

        return $checkoutUrl;
    }

    private function receiptNumberForPayment(Carbon $date, int $paymentId): string
    {
        return 'BT-' . $date->format('Ymd') . '-' . str_pad((string) $paymentId, 4, '0', STR_PAD_LEFT);
    }

    private function findPromo(?string $code, float $subtotal, Carbon $startsAt): ?CodePromo
    {
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $promo = CodePromo::query()->whereRaw('lower(code) = ?', [strtolower($code)])->first();

        if (! $promo || ! $promo->actif) {
            throw ValidationException::withMessages([
                'code_promo' => 'Code promo invalide.',
            ]);
        }

        if ($promo->date_debut && $startsAt->lt($promo->date_debut)) {
            throw ValidationException::withMessages([
                'code_promo' => 'Ce code promo n est pas encore disponible.',
            ]);
        }

        if ($promo->date_fin && $startsAt->gt($promo->date_fin)) {
            throw ValidationException::withMessages([
                'code_promo' => 'Ce code promo est expire.',
            ]);
        }

        if ($promo->limite_utilisation !== null && $promo->nombre_utilisations >= $promo->limite_utilisation) {
            throw ValidationException::withMessages([
                'code_promo' => 'Ce code promo est epuise.',
            ]);
        }

        if ($subtotal <= 0) {
            return null;
        }

        return $promo;
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
}
