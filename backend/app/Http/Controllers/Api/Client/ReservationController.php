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
        } elseif (in_array($payment->mode_paiement, ['wave', 'orange_money'], true)) {
            $checkoutUrl = $this->createPaytechCheckoutSession($payment, $reservation, $client, $data);
        }

        return [
            'message' => match ($payment->mode_paiement) {
                'carte_bancaire' => 'Reservation creee. Continuez vers Stripe pour payer l acompte.',
                'wave', 'orange_money' => 'Reservation creee. Continuez vers PayTech pour payer l acompte.',
                default => 'Paiement enregistre. Le salon validera la transaction avant confirmation.',
            },
            'reservation' => $reservation->load(['client', 'details', 'codePromo']),
            'payment' => $payment->fresh(['reservation', 'client']),
            'checkout_url' => $checkoutUrl,
            'requires_redirect' => $checkoutUrl !== null,
        ];
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
            'notes' => match ($method) {
                'carte_bancaire' => 'Paiement carte bancaire initie depuis l espace client via Stripe.',
                'wave', 'orange_money' => 'Paiement mobile money initie depuis l espace client via PayTech.',
                default => 'Paiement initie depuis l espace client.',
            },
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

    /**
     * @param array<string, mixed> $data
     */
    private function createPaytechCheckoutSession(Paiement $payment, Reservation $reservation, Client $client, array $data): string
    {
        $apiKey = config('services.paytech.api_key');
        $apiSecret = config('services.paytech.api_secret');

        if (! $apiKey || ! $apiSecret) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'PayTech n est pas encore configure.',
            ]);
        }

        $targetPayment = $this->paytechTargetPayment($payment->mode_paiement);
        $refCommand = 'BT-PAY-' . $payment->id . '-' . Str::upper(Str::random(8));
        $successUrl = $this->appendQuery(
            $data['success_url'] ?? config('services.paytech.success_url') ?? config('app.url') . '/client?paiement=paytech_success',
            ['paiement_id' => $payment->id]
        );
        $cancelUrl = $this->appendQuery(
            $data['cancel_url'] ?? config('services.paytech.cancel_url') ?? config('app.url') . '/client?paiement=paytech_cancel',
            ['paiement_id' => $payment->id]
        );
        $ipnUrl = config('services.paytech.ipn_url') ?? config('app.url') . '/api/client/paiements/paytech/ipn';

        $response = Http::asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'API_KEY' => (string) $apiKey,
                'API_SECRET' => (string) $apiSecret,
            ])
            ->post(rtrim((string) config('services.paytech.base_url'), '/') . '/payment/request-payment', [
                'item_name' => "Acompte reservation #{$reservation->id}",
                'item_price' => (int) round((float) $payment->montant),
                'currency' => 'XOF',
                'ref_command' => $refCommand,
                'command_name' => "Acompte reservation #{$reservation->id} - Bichette Thomas",
                'env' => config('services.paytech.env', 'test'),
                'target_payment' => $targetPayment,
                'ipn_url' => $ipnUrl,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'custom_field' => json_encode([
                    'reservation_id' => $reservation->id,
                    'paiement_id' => $payment->id,
                    'ref_command' => $refCommand,
                    'mode_paiement' => $payment->mode_paiement,
                    'email' => $client->email,
                ], JSON_THROW_ON_ERROR),
            ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'PayTech n a pas pu initialiser le paiement.',
            ]);
        }

        $payload = $response->json();
        $checkoutUrl = $payload['redirect_url'] ?? $payload['redirectUrl'] ?? null;
        $token = $payload['token'] ?? null;

        if (($payload['success'] ?? null) !== 1 || ! $checkoutUrl || ! $token) {
            throw ValidationException::withMessages([
                'mode_paiement' => $payload['message'] ?? 'PayTech n a pas retourne de lien de paiement.',
            ]);
        }

        $payment->forceFill([
            'reference' => (string) $token,
            'notes' => trim(($payment->notes ? "{$payment->notes}\n" : '') . "Reference commande PayTech: {$refCommand}"),
        ])->save();

        return $this->appendQuery($checkoutUrl, [
            'pn' => $this->internationalPhone($client->telephone),
            'nn' => $this->nationalPhone($client->telephone),
            'fn' => trim("{$client->prenom} {$client->nom}"),
            'tp' => $targetPayment,
            'nac' => '1',
        ]);
    }

    private function paytechTargetPayment(string $method): string
    {
        return match ($method) {
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            default => 'Orange Money',
        };
    }

    /**
     * @param array<string, int|string> $params
     */
    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    private function internationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '221')) {
            return '+' . $digits;
        }

        return '+221' . ltrim($digits, '0');
    }

    private function nationalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '221')) {
            return substr($digits, 3);
        }

        return ltrim($digits, '0');
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
