<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur la protection CSRF (B4 + B9).
 *
 * Avec auth-via-cookie, toute mutation (POST/PUT/PATCH/DELETE) doit
 * presenter un header X-XSRF-TOKEN egal au cookie XSRF-TOKEN. Sinon 419.
 *
 * Avec auth-via-Bearer header, pas de check CSRF (pas vulnerable par
 * construction : le navigateur ne posera pas un Bearer header sur une
 * cross-origin request, et un Bearer vole exige deja un XSS).
 */
class CsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_authentifie_via_cookie_sans_xsrf_header_renvoie_419(): void
    {
        $auth = $this->loggedInAdmin();

        // Pas de X-XSRF-TOKEN header -> 419 attendu.
        $response = $this->authenticatedAs($auth)
            ->postJson('/api/auth/logout');

        $response->assertStatus(419);
        $response->assertJsonPath('message', 'CSRF token mismatch.');
    }

    public function test_post_authentifie_via_cookie_avec_mauvais_xsrf_header_renvoie_419(): void
    {
        $auth = $this->loggedInAdmin();

        $response = $this->authenticatedAs($auth)
            ->withHeaders(['X-XSRF-TOKEN' => 'mauvaise-valeur-quelconque'])
            ->postJson('/api/auth/logout');

        $response->assertStatus(419);
    }

    public function test_post_authentifie_via_cookie_avec_bon_xsrf_header_passe(): void
    {
        $auth = $this->loggedInAdmin();

        $response = $this->authenticatedAs($auth)
            ->withHeaders(['X-XSRF-TOKEN' => $auth['csrf']])
            ->postJson('/api/auth/logout');

        $response->assertOk();
    }

    public function test_get_authentifie_via_cookie_n_a_pas_besoin_de_xsrf(): void
    {
        // Methodes safe (GET/HEAD/OPTIONS) ne sont pas concernees par CSRF.
        $auth = $this->loggedInAdmin();

        $response = $this->authenticatedAs($auth)->getJson('/api/auth/me');

        $response->assertOk();
    }
}
