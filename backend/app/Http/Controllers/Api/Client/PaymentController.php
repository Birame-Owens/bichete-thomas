<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    private const INCOMING_TYPES = ['acompte', 'solde', 'complet', 'ajustement'];

    public function confirmStripeCheckout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);

        $session = $this->retrieveStripeSession($data['session_id']);

        if (($session['payment_status'] ?? null) !== 'paid') {
            throw ValidationException::withMessages([
                'session_id' => 'Le paiement carte bancaire n est pas encore confirme par Stripe.',
            ]);
        }

        $payment = $this->paymentFromStripeSession($session);
        $this->markPaymentAsPaid($payment, (string) $session['id']);

        return response()->json([
            'message' => 'Paiement carte valide. Votre reservation est securisee.',
            'data' => $payment->fresh(['reservation.client', 'client']),
        ]);
    }

    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        if (! $this->stripeSignatureIsValid($payload, (string) $request->header('Stripe-Signature'))) {
            return response()->json(['message' => 'Signature Stripe invalide.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return response()->json(['message' => 'Payload Stripe invalide.'], 400);
        }

        if (($event['type'] ?? null) === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];

            if (($session['payment_status'] ?? null) === 'paid') {
                try {
                    $payment = $this->paymentFromStripeSession($session);
                    $this->markPaymentAsPaid($payment, (string) $session['id']);
                } catch (\Throwable $exception) {
                    Log::warning('Stripe webhook payment sync failed', [
                        'message' => $exception->getMessage(),
                        'session_id' => $session['id'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    public function paytechWebhook(Request $request): JsonResponse
    {
        if (! $this->paytechSignatureIsValid($request)) {
            return response()->json(['message' => 'IPN PayTech invalide.'], 403);
        }

        $payment = $this->paymentFromPaytechPayload($request);
        $event = $request->string('type_event')->toString();
        $reference = $request->string('token')->toString()
            ?: $request->string('ref_command')->toString()
            ?: (string) $payment->reference;

        if ($event === 'sale_complete') {
            $this->markPaymentAsPaid($payment, $reference);
        } elseif ($event === 'sale_canceled') {
            $this->markPaymentAsCanceled($payment, $reference);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function retrieveStripeSession(string $sessionId): array
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw ValidationException::withMessages([
                'session_id' => 'Stripe n est pas configure.',
            ]);
        }

        $response = Http::withToken((string) $secret)
            ->get('https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId));

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'session_id' => 'Impossible de verifier la session Stripe.',
            ]);
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $session
     */
    private function paymentFromStripeSession(array $session): Paiement
    {
        $paymentId = $session['metadata']['paiement_id'] ?? null;
        $sessionId = $session['id'] ?? null;

        $payment = Paiement::query()
            ->when($paymentId, fn (Builder $query) => $query->whereKey((int) $paymentId))
            ->when(! $paymentId && $sessionId, fn (Builder $query) => $query->where('reference', $sessionId))
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'session_id' => 'Paiement introuvable pour cette session Stripe.',
            ]);
        }

        return $payment;
    }

    private function stripeSignatureIsValid(string $payload, string $signatureHeader): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            return true;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return $key && $value ? [$key => $value] : [];
            });
        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) $secret);

        return hash_equals($expected, (string) $signature);
    }

    private function paytechSignatureIsValid(Request $request): bool
    {
        $apiKey = config('services.paytech.api_key');
        $apiSecret = config('services.paytech.api_secret');

        if (! $apiKey || ! $apiSecret) {
            return false;
        }

        $hmac = $request->string('hmac_compute')->toString();

        if ($hmac !== '') {
            $amount = $request->input('final_item_price', $request->input('item_price'));
            $refCommand = $request->string('ref_command')->toString();
            $expected = hash_hmac('sha256', "{$amount}|{$refCommand}|{$apiKey}", (string) $apiSecret);

            return hash_equals($expected, $hmac);
        }

        return hash_equals(hash('sha256', (string) $apiKey), $request->string('api_key_sha256')->toString())
            && hash_equals(hash('sha256', (string) $apiSecret), $request->string('api_secret_sha256')->toString());
    }

    private function paymentFromPaytechPayload(Request $request): Paiement
    {
        $custom = $this->decodePaytechCustomField($request->string('custom_field')->toString());
        $paymentId = $custom['paiement_id'] ?? null;
        $token = $request->string('token')->toString();
        $refCommand = $request->string('ref_command')->toString();

        $payment = Paiement::query()
            ->when($paymentId, fn (Builder $query) => $query->whereKey((int) $paymentId))
            ->when(! $paymentId && $token !== '', fn (Builder $query) => $query->where('reference', $token))
            ->when(! $paymentId && $token === '' && $refCommand !== '', fn (Builder $query) => $query->where('notes', 'ilike', "%{$refCommand}%"))
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'ref_command' => 'Paiement PayTech introuvable.',
            ]);
        }

        return $payment;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePaytechCustomField(string $customField): array
    {
        foreach ([$customField, base64_decode($customField, true) ?: ''] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function markPaymentAsPaid(Paiement $payment, string $reference): void
    {
        $payment->forceFill([
            'statut' => 'valide',
            'reference' => $reference,
            'date_paiement' => now(),
        ])->save();

        $this->syncReservationPaymentState($payment->reservation_id);
    }

    private function markPaymentAsCanceled(Paiement $payment, string $reference): void
    {
        $payment->forceFill([
            'statut' => 'annule',
            'reference' => $reference,
            'date_paiement' => now(),
        ])->save();

        $reservation = $payment->reservation_id ? Reservation::query()->find($payment->reservation_id) : null;

        if ($reservation && in_array($reservation->statut, ['en_attente', 'confirmee'], true)) {
            $paid = $this->reservationPaidAmount($reservation->id);

            if ($paid <= 0) {
                $reservation->forceFill([
                    'statut' => 'annulee',
                    'annulee_at' => now(),
                ])->save();
            }
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

        $reservation->forceFill($updates)->save();
    }

    private function reservationPaidAmount(int $reservationId): float
    {
        $incoming = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->where('statut', 'valide')
            ->whereIn('type', self::INCOMING_TYPES)
            ->sum('montant');

        $refunds = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('statut', ['valide', 'rembourse'])
            ->where('type', 'remboursement')
            ->sum('montant');

        return max((float) $incoming - (float) $refunds, 0);
    }
}
