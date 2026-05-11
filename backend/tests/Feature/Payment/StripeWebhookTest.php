<?php

namespace Tests\Feature\Payment;

use App\Jobs\SendPaymentReceiptNotifications;
use App\Models\Client;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Tests d integration sur POST /api/client/paiements/stripe/webhook (B9).
 *
 * Couvre :
 * - signature Stripe valide -> paiement marque "valide" + job notification dispatched
 * - signature Stripe invalide -> 400, paiement reste en attente
 * - event non gere (autre que checkout.session.completed) -> ignore proprement
 *
 * Le service PaymentReceiptNotificationService est mock par Bus::fake (le job
 * est dispatch mais pas execute).
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => 'whsec_test_fake']);
    }

    public function test_webhook_stripe_valide_marque_paiement_et_dispatche_job(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment();

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'metadata' => [
                        'paiement_id' => (string) $payment->id,
                    ],
                ],
            ],
        ]);

        $signature = $this->signStripePayload($payload);

        $response = $this->call(
            method: 'POST',
            uri: '/api/client/paiements/stripe/webhook',
            server: [
                'HTTP_Stripe-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );

        $response->assertOk();
        $response->assertJsonPath('received', true);

        // Paiement passe en "valide".
        $this->assertSame('valide', $payment->fresh()->statut);
        $this->assertSame('cs_test_123', $payment->fresh()->reference);

        // Job d envoi de recu dispatched (I6).
        Bus::assertDispatched(SendPaymentReceiptNotifications::class, function ($job) use ($payment) {
            return $job->paiementId === $payment->id;
        });
    }

    public function test_webhook_stripe_avec_signature_invalide_retourne_400(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment();

        $payload = json_encode(['type' => 'checkout.session.completed']);

        $response = $this->call(
            method: 'POST',
            uri: '/api/client/paiements/stripe/webhook',
            server: [
                'HTTP_Stripe-Signature' => 't=1234,v1=signature_completement_inventee',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );

        $response->assertStatus(400);

        // Paiement reste en attente.
        $this->assertSame('en_attente', $payment->fresh()->statut);
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    public function test_webhook_stripe_rejoue_ne_dispatche_pas_un_second_recu(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment();

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_idempotent',
                    'payment_status' => 'paid',
                    'metadata' => ['paiement_id' => (string) $payment->id],
                ],
            ],
        ]);

        $signature = $this->signStripePayload($payload);
        $headers = ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'];

        // Premier appel : valide le paiement.
        $this->call('POST', '/api/client/paiements/stripe/webhook', server: $headers, content: $payload)->assertOk();
        $this->assertSame('valide', $payment->fresh()->statut);
        Bus::assertDispatchedTimes(SendPaymentReceiptNotifications::class, 1);

        // Deuxieme appel (webhook retente par Stripe) : no-op, pas de second dispatch.
        $this->call('POST', '/api/client/paiements/stripe/webhook', server: $headers, content: $payload)->assertOk();
        Bus::assertDispatchedTimes(SendPaymentReceiptNotifications::class, 1);
    }

    public function test_webhook_stripe_event_non_gere_repond_received_sans_modif(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment();

        $payload = json_encode([
            'type' => 'customer.created', // Event qu on n ecoute pas.
            'data' => ['object' => []],
        ]);

        $signature = $this->signStripePayload($payload);

        $response = $this->call(
            method: 'POST',
            uri: '/api/client/paiements/stripe/webhook',
            server: [
                'HTTP_Stripe-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $payload,
        );

        $response->assertOk();
        $this->assertSame('en_attente', $payment->fresh()->statut);
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function signStripePayload(string $payload): string
    {
        $timestamp = time();
        $secret = (string) config('services.stripe.webhook_secret');
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$signature}";
    }

    private function createPendingPayment(): Paiement
    {
        $client = Client::query()->create([
            'nom' => 'Test',
            'prenom' => 'Client',
            'telephone' => '+221770000000',
            'source' => 'en_ligne',
        ]);

        $reservation = Reservation::query()->create([
            'client_id' => $client->id,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '11:00',
            'duree_totale_minutes' => 60,
            'statut' => 'en_attente',
            'source' => 'en_ligne',
            'montant_total' => 15000,
            'montant_acompte' => 5000,
            'montant_reduction' => 0,
            'montant_restant' => 10000,
            'devise' => 'FCFA',
        ]);

        return Paiement::query()->create([
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'numero_recu' => 'TEST-001',
            'type' => 'acompte',
            'mode_paiement' => 'carte_bancaire',
            'montant' => 5000,
            'devise' => 'FCFA',
            'statut' => 'en_attente',
            'date_paiement' => now(),
            'recu_envoye' => false,
        ]);
    }
}
