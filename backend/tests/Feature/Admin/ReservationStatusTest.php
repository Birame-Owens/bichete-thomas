<?php

namespace Tests\Feature\Admin;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur PATCH /api/admin/reservations/{id}/statut.
 *
 * Couvre :
 * - transitions valides avec et sans notes
 * - transitions non autorisees refusees en 422
 * - acces refuse sans token (401) et avec token gerante (403)
 *
 * Note : l admin ne cree pas de paiement lors du changement de statut
 * (contrairement a la gerante). La creation de paiement passe par
 * POST /api/admin/paiements.
 */
class ReservationStatusTest extends TestCase
{
    use RefreshDatabase;

    // ─── Acces ───────────────────────────────────────────────────────────────

    public function test_sans_token_refuse_401(): void
    {
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
            'statut' => 'confirmee',
        ])->assertStatus(401);
    }

    public function test_token_gerante_refuse_403(): void
    {
        $auth        = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertStatus(403);
    }

    // ─── Transitions valides ─────────────────────────────────────────────────

    public function test_en_attente_vers_confirmee(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirmee');
    }

    public function test_confirmee_vers_en_cours(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'confirmee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_en_cours_vers_terminee(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_restant' => 0,
        ]);

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'terminee',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'terminee');

        $this->assertNotNull(
            Reservation::find($reservation->id)->terminee_at
        );
    }

    public function test_statut_accepte_une_note(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
                'notes'  => 'Annulation a la demande de la cliente.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee');
    }

    public function test_en_attente_vers_absence(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'absence',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'absence');
    }

    // ─── Transitions non autorisees ──────────────────────────────────────────

    public function test_terminee_vers_en_cours_refuse_422(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'terminee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['statut']);
    }

    public function test_annulee_vers_confirmee_refuse_422(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'annulee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['statut']);
    }

    public function test_statut_invalide_refuse_422(): void
    {
        $auth        = $this->loggedInAdmin();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'inexistant',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['statut']);
    }
}
