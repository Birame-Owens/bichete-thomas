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
 * Tests d integration sur POST /api/client/paiements/paytech/ipn (B9).
 *
 * PayTech accepte 2 modes de signature pour ses IPN :
 * 1. HMAC-SHA256 via le champ `hmac_compute` :
 *    hash_hmac('sha256', "{amount}|{refCommand}|{apiKey}", apiSecret)
 * 2. Double hash sha256 des cles API en clair (api_key_sha256 + api_secret_sha256)
 *
 * Couvre :
 * - signature HMAC valide + sale_complete -> markPaymentAsPaid + dispatch job
 * - signature double-hash valide + sale_complete -> idem
 * - signature invalide -> 403
 * - sale_canceled -> markPaymentAsCanceled
 */
class PaytechWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'paytech-api-key-fake';
    private const API_SECRET = 'paytech-api-secret-fake';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.paytech.api_key' => self::API_KEY,
            'services.paytech.api_secret' => self::API_SECRET,
        ]);
    }

    public function test_webhook_paytech_hmac_valide_marque_paiement_et_dispatche_job(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment(5000);

        $payload = $this->paytechPayload(
            paiementId: $payment->id,
            amount: 5000,
            refCommand: 'BT-PAY-1-ABC',
            event: 'sale_complete',
        );

        $response = $this->postJson('/api/client/paiements/paytech/ipn', $payload);

        $response->assertOk();
        $response->assertJsonPath('received', true);

        $this->assertSame('valide', $payment->fresh()->statut);

        Bus::assertDispatched(SendPaymentReceiptNotifications::class, function ($job) use ($payment) {
            return $job->paiementId === $payment->id;
        });
    }

    public function test_webhook_paytech_double_hash_valide_marque_paiement(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment(5000);

        // Mode signature alternatif : envoi des hash sha256 des cles plutot
        // que d un hmac calcule sur le contenu.
        $payload = [
            'type_event' => 'sale_complete',
            'item_price' => 5000,
            'ref_command' => 'BT-PAY-1-XYZ',
            'token' => 'paytech-token-xyz',
            'custom_field' => json_encode(['paiement_id' => $payment->id]),
            'api_key_sha256' => hash('sha256', self::API_KEY),
            'api_secret_sha256' => hash('sha256', self::API_SECRET),
        ];

        $response = $this->postJson('/api/client/paiements/paytech/ipn', $payload);

        $response->assertOk();
        $this->assertSame('valide', $payment->fresh()->statut);
        Bus::assertDispatched(SendPaymentReceiptNotifications::class);
    }

    public function test_webhook_paytech_avec_signature_invalide_retourne_403(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment(5000);

        // hmac_compute completement invente.
        $payload = [
            'type_event' => 'sale_complete',
            'item_price' => 5000,
            'ref_command' => 'BT-PAY-1-FAKE',
            'custom_field' => json_encode(['paiement_id' => $payment->id]),
            'hmac_compute' => 'signature_completement_inventee',
        ];

        $response = $this->postJson('/api/client/paiements/paytech/ipn', $payload);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'IPN PayTech invalide.');

        // Le paiement reste en attente.
        $this->assertSame('en_attente', $payment->fresh()->statut);
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    public function test_webhook_paytech_sale_canceled_annule_le_paiement(): void
    {
        Bus::fake();
        $payment = $this->createPendingPayment(5000);

        $payload = $this->paytechPayload(
            paiementId: $payment->id,
            amount: 5000,
            refCommand: 'BT-PAY-1-CANCEL',
            event: 'sale_canceled',
        );

        $response = $this->postJson('/api/client/paiements/paytech/ipn', $payload);

        $response->assertOk();
        $this->assertSame('annule', $payment->fresh()->statut);

        // Pas de dispatch sur cancel : pas de recu a envoyer.
        Bus::assertNotDispatched(SendPaymentReceiptNotifications::class);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Construit un payload PayTech IPN avec une signature HMAC valide.
     *
     * @return array<string, mixed>
     */
    private function paytechPayload(int $paiementId, int $amount, string $refCommand, string $event): array
    {
        $hmac = hash_hmac(
            'sha256',
            "{$amount}|{$refCommand}|" . self::API_KEY,
            self::API_SECRET,
        );

        return [
            'type_event' => $event,
            'item_price' => $amount,
            'final_item_price' => $amount,
            'ref_command' => $refCommand,
            'token' => 'paytech-token-' . $paiementId,
            'custom_field' => json_encode(['paiement_id' => $paiementId]),
            'hmac_compute' => $hmac,
        ];
    }

    private function createPendingPayment(int $montant): Paiement
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
            'montant_acompte' => $montant,
            'montant_reduction' => 0,
            'montant_restant' => 15000 - $montant,
            'devise' => 'FCFA',
        ]);

        return Paiement::query()->create([
            'reservation_id' => $reservation->id,
            'client_id' => $client->id,
            'numero_recu' => 'TEST-PAY-001',
            'type' => 'acompte',
            'mode_paiement' => 'wave',
            'montant' => $montant,
            'devise' => 'FCFA',
            'statut' => 'en_attente',
            'date_paiement' => now(),
            'recu_envoye' => false,
        ]);
    }
}
