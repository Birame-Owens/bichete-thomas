<?php

namespace Tests\Feature\Payment;

use App\Jobs\SendPaymentReceiptNotifications;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NaboopayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'naboopay-webhook-secret-fake';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.naboopay.api_key' => 'naboopay-api-key-fake',
            'services.naboopay.webhook_secret' => self::WEBHOOK_SECRET,
        ]);
    }

    public function test_webhook_naboopay_valide_marque_paiement_et_dispatche_job(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment('naboo-order-123');
        $payload = [
            'order_id' => 'naboo-order-123',
            'transaction_status' => 'completed',
        ];

        $response = $this->postSignedWebhook($payload);

        $response->assertOk();
        $response->assertJsonPath('received', true);
        $this->assertSame('valide', $payment->fresh()->statut);

        Bus::assertDispatched(SendPaymentReceiptNotifications::class, function ($job) use ($payment) {
            return $job->paiementId === $payment->id;
        });
    }

    public function test_webhook_naboopay_signature_invalide_retourne_401(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment('naboo-order-invalid');

        $response = $this->postJson('/api/client/paiements/naboopay/webhook', [
            'order_id' => 'naboo-order-invalid',
            'transaction_status' => 'completed',
        ], [
            'X-Signature' => 'signature-invalide',
        ]);

        $response->assertStatus(401);
        $this->assertSame('en_attente', $payment->fresh()->statut);
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    public function test_webhook_naboopay_rejoue_ne_dispatche_pas_un_second_recu(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment('naboo-order-retry');
        $payload = [
            'order_id' => 'naboo-order-retry',
            'transaction_status' => 'completed',
        ];

        $this->postSignedWebhook($payload)->assertOk();
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame('valide', $payment->fresh()->statut);
        Bus::assertDispatchedTimes(SendPaymentReceiptNotifications::class, 1);
    }

    public function test_webhook_naboopay_echec_annule_le_paiement(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment('naboo-order-failed');

        $this->postSignedWebhook([
            'order_id' => 'naboo-order-failed',
            'transaction_status' => 'failed',
        ])->assertOk();

        $this->assertSame('annule', $payment->fresh()->statut);
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postSignedWebhook(array $payload)
    {
        $content = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $content, self::WEBHOOK_SECRET);

        return $this->call(
            'POST',
            '/api/client/paiements/naboopay/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            content: (string) $content,
        );
    }

    private function createPendingPayment(string $reference): Paiement
    {
        return Paiement::factory()->create([
            'reference' => $reference,
            'mode_paiement' => 'wave',
        ]);
    }
}
