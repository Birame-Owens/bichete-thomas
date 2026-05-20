<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifie que les middlewares role:admin et role:gerante sont en place
 * et que personne ne peut acceder a un espace qui n est pas le sien.
 *
 * Ces tests sont le filet de securite le plus important : si quelqu un
 * retire accidentellement un middleware de route, une gerante pourrait
 * obtenir un acces admin complet sans que personne ne le remarque
 * jusqu en production. Ce test l attrape immediatement.
 */
class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    // ─── Sans token ──────────────────────────────────────────────────────────

    public function test_sans_token_acces_admin_refuse_401(): void
    {
        $this->getJson('/api/admin/reservations')->assertStatus(401);
    }

    public function test_sans_token_acces_gerante_refuse_401(): void
    {
        $this->getJson('/api/gerante/reservations')->assertStatus(401);
    }

    public function test_sans_token_acces_admin_clients_refuse_401(): void
    {
        $this->getJson('/api/admin/clients')->assertStatus(401);
    }

    public function test_sans_token_acces_dashboard_refuse_401(): void
    {
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }

    // ─── Gerante ne peut pas acceder a l espace admin ────────────────────────

    public function test_gerante_ne_peut_pas_acceder_aux_reservations_admin(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/reservations')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Acces non autorise pour ce role.');
    }

    public function test_gerante_ne_peut_pas_acceder_aux_clients_admin(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/clients')
            ->assertStatus(403);
    }

    public function test_gerante_ne_peut_pas_acceder_au_dashboard_admin(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/dashboard')
            ->assertStatus(403);
    }

    public function test_gerante_ne_peut_pas_acceder_aux_paiements_admin(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/paiements')
            ->assertStatus(403);
    }

    public function test_gerante_ne_peut_pas_acceder_aux_logs_admin(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/logs-systeme')
            ->assertStatus(403);
    }

    public function test_gerante_ne_peut_pas_acceder_a_la_caisse(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/admin/caisses/du-jour')
            ->assertStatus(403);
    }

    // ─── Admin ne peut pas acceder a l espace gerante ────────────────────────

    public function test_admin_ne_peut_pas_acceder_aux_reservations_gerante(): void
    {
        $auth = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->getJson('/api/gerante/reservations')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Acces non autorise pour ce role.');
    }

    public function test_admin_ne_peut_pas_changer_statut_via_route_gerante(): void
    {
        $auth = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson('/api/gerante/reservations/1/statut', ['statut' => 'confirmee'])
            ->assertStatus(403);
    }
}
