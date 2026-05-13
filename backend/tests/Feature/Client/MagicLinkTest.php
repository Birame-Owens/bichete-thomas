<?php

namespace Tests\Feature\Client;

use App\Jobs\SendMagicLinkNotification;
use App\Models\Client;
use App\Models\Paiement;
use App\Services\ClientMagicLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Tests d integration Phase 5 etape 2 : magic link + session client.
 *
 * Couvre :
 * - Token valide -> session posee, cookie present, donnees client retournees
 * - Token expire -> 422
 * - Token deja utilise (single-use) -> 422
 * - Token inconnu -> 422
 * - GET /session avec cookie valide -> donnees client
 * - GET /session sans cookie -> 401
 * - DELETE /session -> cookie efface, token revoque en DB
 * - Client blackliste -> session rejetee
 * - Paiement confirme -> job SendMagicLinkNotification dispatche
 */
class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // POST /api/client/auth/magic-link (verification du token)
    // -----------------------------------------------------------------

    public function test_token_valide_pose_un_cookie_et_retourne_les_donnees_client(): void
    {
        $client = $this->createClient();
        $service = app(ClientMagicLinkService::class);
        $rawToken = $service->generateMagicLink($client);

        $response = $this->postJson('/api/client/auth/magic-link', ['token' => $rawToken]);

        $response->assertOk();
        $response->assertJsonPath('data.nom', $client->nom);
        $response->assertJsonPath('data.prenom', $client->prenom);
        $response->assertJsonPath('data.telephone', $client->telephone);
        $response->assertCookie('bt_client_session');

        // Token consomme en DB (single-use).
        $this->assertNull($client->fresh()->magic_link_token);
    }

    public function test_token_expire_retourne_422(): void
    {
        $client = $this->createClient();
        $service = app(ClientMagicLinkService::class);
        $rawToken = $service->generateMagicLink($client);

        // Expire manuellement le token.
        $client->forceFill(['magic_link_expires_at' => now()->subMinute()])->save();

        $response = $this->postJson('/api/client/auth/magic-link', ['token' => $rawToken]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Lien invalide ou expiré.');
        $response->assertCookieMissing('bt_client_session');
    }

    public function test_token_deja_utilise_retourne_422(): void
    {
        $client = $this->createClient();
        $service = app(ClientMagicLinkService::class);
        $rawToken = $service->generateMagicLink($client);

        // Premier appel consomme le token.
        $this->postJson('/api/client/auth/magic-link', ['token' => $rawToken])->assertOk();

        // Deuxieme appel sur le meme token.
        $response = $this->postJson('/api/client/auth/magic-link', ['token' => $rawToken]);

        $response->assertStatus(422);
    }

    public function test_token_inconnu_retourne_422(): void
    {
        $response = $this->postJson('/api/client/auth/magic-link', [
            'token' => str_repeat('a', 64),
        ]);

        $response->assertStatus(422);
    }

    public function test_token_trop_court_echoue_la_validation(): void
    {
        $response = $this->postJson('/api/client/auth/magic-link', ['token' => 'court']);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    // -----------------------------------------------------------------
    // GET /api/client/session
    // -----------------------------------------------------------------

    public function test_session_avec_cookie_valide_retourne_les_donnees_client(): void
    {
        $client = $this->createClient();
        $cookies = $this->loginClient($client);

        $response = $this->withCredentials()->withUnencryptedCookies($cookies)
            ->getJson('/api/client/session');

        $response->assertOk();
        $response->assertJsonPath('data.nom', $client->nom);
        $response->assertJsonPath('data.prenom', $client->prenom);
        $response->assertJsonPath('data.telephone', $client->telephone);
    }

    public function test_session_sans_cookie_retourne_401(): void
    {
        $response = $this->getJson('/api/client/session');

        $response->assertStatus(401);
    }

    public function test_session_avec_cookie_expire_retourne_401(): void
    {
        $client = $this->createClient();
        $cookies = $this->loginClient($client);

        // Expire la session en DB apres l'avoir creee via verify.
        $client->forceFill(['session_expires_at' => now()->subMinute()])->save();

        $response = $this->withCredentials()->withUnencryptedCookies($cookies)
            ->getJson('/api/client/session');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // DELETE /api/client/session
    // -----------------------------------------------------------------

    public function test_logout_revoque_le_token_et_efface_le_cookie(): void
    {
        $client = $this->createClient();
        $cookies = $this->loginClient($client);

        $response = $this->withCredentials()->withUnencryptedCookies($cookies)
            ->deleteJson('/api/client/session');

        $response->assertOk();

        // Token revoque en DB.
        $this->assertNull($client->fresh()->session_token);

        // La session suivante doit etre rejetee (meme cookie chiffre, token efface en DB).
        $this->withCredentials()->withUnencryptedCookies($cookies)
            ->getJson('/api/client/session')
            ->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // Client blackliste
    // -----------------------------------------------------------------

    public function test_client_blackliste_ne_peut_pas_utiliser_sa_session(): void
    {
        $client = $this->createClient();
        $cookies = $this->loginClient($client);

        $client->forceFill(['est_blackliste' => true])->save();

        $response = $this->withCredentials()->withUnencryptedCookies($cookies)
            ->getJson('/api/client/session');

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------
    // Dispatch du job apres paiement confirme
    // -----------------------------------------------------------------

    public function test_paiement_valide_dispatche_le_job_magic_link(): void
    {
        Bus::fake();
        config([
            'services.paytech.api_key' => 'fake-key',
            'services.paytech.api_secret' => 'fake-secret',
        ]);

        $payment = $this->createPendingPayment();

        $hmac = hash_hmac('sha256', "5000|BT-PAY-1-ML|fake-key", 'fake-secret');
        $this->postJson('/api/client/paiements/paytech/ipn', [
            'type_event' => 'sale_complete',
            'item_price' => 5000,
            'final_item_price' => 5000,
            'ref_command' => 'BT-PAY-1-ML',
            'token' => 'tok-ml',
            'custom_field' => json_encode(['paiement_id' => $payment->id]),
            'hmac_compute' => $hmac,
        ])->assertOk();

        Bus::assertDispatched(SendMagicLinkNotification::class, fn ($job) => $job->paiementId === $payment->id);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    /**
     * Cree une session client valide via l'endpoint verify et retourne
     * le cookie chiffre pret a etre passe avec withUnencryptedCookies().
     *
     * Meme patron que TestCase::authenticatedAs() pour les admins :
     * on passe par le vrai endpoint pour que Laravel chiffre le cookie
     * lui-meme, puis on rejoue ce cookie chiffre dans les requetes suivantes.
     *
     * @return array<string, string>
     */
    private function loginClient(Client $client): array
    {
        $service = app(ClientMagicLinkService::class);
        $rawToken = $service->generateMagicLink($client);

        $verifyResponse = $this->postJson('/api/client/auth/magic-link', ['token' => $rawToken]);
        $verifyResponse->assertOk();

        $cookies = [];
        foreach ($verifyResponse->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'bt_client_session') {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $cookies;
    }

    private function createClient(): Client
    {
        return Client::factory()->create();
    }

    private function createPendingPayment(): Paiement
    {
        $client = $this->createClient();

        return Paiement::factory()->create(['client_id' => $client->id]);
    }
}
