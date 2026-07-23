<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur AuthenticateApiToken middleware (B9).
 *
 * Couvre :
 * - acces protege sans cookie -> 401
 * - acces protege avec cookie valide -> 200
 * - acces protege avec cookie expire (inactivite I1) -> 401
 * - acces protege avec compte desactive -> 403
 * - logout invalide le token cote serveur
 */
class AuthMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_protege_refuse_sans_cookie(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Token absent.');
    }

    public function test_endpoint_protege_accepte_un_cookie_valide(): void
    {
        $auth = $this->loggedInAdmin();

        $response = $this->authenticatedAs($auth)->getJson('/api/auth/me');

        $response->assertOk();
        $response->assertJsonPath('user.email', $auth['user']->email);
    }

    public function test_session_expirée_pour_inactivite_invalide_le_token(): void
    {
        $auth = $this->loggedInAdmin();

        // 1h au-dela de la sliding window configuree (I1) -> middleware doit
        // rejeter. Derive de la config pour rester valide si la fenetre change
        // (elle est passee de 6h a 2h en cours de projet).
        $window = (int) config('auth.session_inactivity_hours', 2);
        PersonalAccessToken::query()->update([
            'last_used_at' => now()->subHours($window + 1),
        ]);

        $response = $this->authenticatedAs($auth)->getJson('/api/auth/me');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Session expiree pour cause d inactivite.');
    }

    public function test_session_active_dans_la_fenetre_d_inactivite_reste_valide(): void
    {
        $auth = $this->loggedInAdmin();

        // 1h en-deca de la fenetre configuree -> doit passer.
        $window = (int) config('auth.session_inactivity_hours', 2);
        PersonalAccessToken::query()->update([
            'last_used_at' => now()->subHours(max($window - 1, 0)),
        ]);

        $response = $this->authenticatedAs($auth)->getJson('/api/auth/me');

        $response->assertOk();
    }

    public function test_compte_desactive_bloque_acces_meme_avec_cookie_valide(): void
    {
        $auth = $this->loggedInAdmin();

        $auth['user']->update(['actif' => false]);

        $response = $this->authenticatedAs($auth)->getJson('/api/auth/me');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Compte desactive.');
    }

    public function test_logout_supprime_le_token_et_invalide_les_cookies(): void
    {
        $auth = $this->loggedInAdmin();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Les cookies retournes ont une date d expiration dans le passe
        // (Cookie::forget) -> le navigateur les supprime.
        $cookies = $response->headers->getCookies();
        $authCookie = collect($cookies)->first(fn ($c) => $c->getName() === AuthController::AUTH_COOKIE);
        $this->assertNotNull($authCookie);
        $this->assertLessThan(time(), $authCookie->getExpiresTime());
    }
}
