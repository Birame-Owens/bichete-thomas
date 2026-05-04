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

    private function markPaymentAsPaid(Paiement $payment, string $reference): void
    {
        $payment->forceFill([
            'statut' => 'valide',
            'reference' => $reference,
            'date_paiement' => now(),
        ])->save();

        $this->syncReservationPaymentState($payment->reservation_id);
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
