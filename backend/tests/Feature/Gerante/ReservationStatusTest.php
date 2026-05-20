<?php

namespace Tests\Feature\Gerante;

use App\Models\LogSysteme;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur PATCH /api/gerante/reservations/{id}/statut.
 *
 * Couvre :
 * - transitions valides (confirmee, en_cours, terminee, annulee, absence)
 * - terminee sans solde restant
 * - terminee avec solde : paiement cree, numero_recu bien formate (BT-YYYYMMDD-XXXX)
 * - terminee avec solde : option de ne pas enregistrer le paiement
 * - transition sensible (acompte_paye -> annulee) : raison obligatoire + log alerte
 * - transition sensible (acompte_paye -> absence) : meme exigence
 * - raison trop courte (<20 caracteres) refuse en 422
 * - transition non autorisee refuse en 422
 * - acces sans token refuse en 401
 */
class ReservationStatusTest extends TestCase
{
    use RefreshDatabase;

    // ─── Acces ───────────────────────────────────────────────────────────────

    public function test_sans_token_acces_refuse_401(): void
    {
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
            'statut' => 'confirmee',
        ])->assertStatus(401);
    }

    // ─── Transitions simples ─────────────────────────────────────────────────

    public function test_en_attente_vers_confirmee(): void
    {
        $auth        = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'confirmee');
    }

    public function test_confirmee_vers_en_cours(): void
    {
        $auth        = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'confirmee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');
    }

    public function test_en_cours_vers_annulee(): void
    {
        $auth        = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'en_cours']);

        // Sans raison : refuse (en_cours est dans SENSITIVE_TRANSITIONS comme acompte_paye)
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['raison']);

        // Avec raison suffisamment longue : accepte
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
                'raison' => 'Cliente ne sest pas presentee apres paiement complet.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee');
    }

    // ─── Terminee sans solde restant ─────────────────────────────────────────

    public function test_en_cours_vers_terminee_sans_solde_restant(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_restant' => 0,
        ]);

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'terminee',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'terminee');

        // terminee_at doit etre renseigne.
        $this->assertNotNull(
            Reservation::find($reservation->id)->terminee_at
        );

        // Aucun paiement cree.
        $this->assertDatabaseCount('paiements', 0);
    }

    // ─── Terminee avec solde : paiement enregistre ───────────────────────────

    public function test_en_cours_vers_terminee_avec_solde_enregistre(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_total'   => 20000,
            'montant_restant' => 15000,
            'devise'          => 'FCFA',
        ]);

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut'               => 'terminee',
                'enregistrer_paiement' => true,
                'mode_paiement_solde'  => 'especes',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.statut', 'terminee');

        // Un paiement de type solde doit etre cree.
        $this->assertDatabaseCount('paiements', 1);
        $paiement = Paiement::first();

        $this->assertSame('solde', $paiement->type);
        $this->assertSame('especes', $paiement->mode_paiement);
        $this->assertSame('valide', $paiement->statut);
        $this->assertEquals(15000, $paiement->montant);

        // numero_recu doit respecter le format BT-YYYYMMDD-XXXX.
        $this->assertMatchesRegularExpression(
            '/^BT-\d{8}-\d{4}$/',
            $paiement->numero_recu
        );

        // montant_restant remis a zero en base.
        $this->assertEquals(0, Reservation::find($reservation->id)->montant_restant);
    }

    public function test_enregistrer_paiement_requis_quand_solde_positif(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_restant' => 15000,
        ]);

        // Champ enregistrer_paiement absent alors que montant_restant > 0.
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'terminee',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['enregistrer_paiement']);
    }

    public function test_mode_paiement_requis_si_enregistrer_paiement_true(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_restant' => 15000,
        ]);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut'               => 'terminee',
                'enregistrer_paiement' => true,
                // mode_paiement_solde absent
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode_paiement_solde']);
    }

    public function test_en_cours_vers_terminee_avec_solde_non_enregistre(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'en_cours',
            'montant_restant' => 15000,
        ]);

        // La gerante choisit de ne pas enregistrer le paiement maintenant.
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut'               => 'terminee',
                'enregistrer_paiement' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'terminee');

        // Aucun paiement cree.
        $this->assertDatabaseCount('paiements', 0);
    }

    // ─── Transitions sensibles (post-acompte) ────────────────────────────────

    public function test_acompte_paye_vers_annulee_sans_raison_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'acompte_paye']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['raison']);
    }

    public function test_acompte_paye_vers_annulee_avec_raison_trop_courte_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'acompte_paye']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
                'raison' => 'Trop court',  // < 20 caracteres
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['raison']);
    }

    public function test_acompte_paye_vers_annulee_avec_raison_valide_cree_log_alerte(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create([
            'statut'          => 'acompte_paye',
            'montant_acompte' => 5000,
        ]);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'annulee',
                'raison' => 'Annulation demandee par la cliente pour raison personnelle.',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'annulee');

        // Un log d alerte doit etre cree avec l action speciale.
        $this->assertDatabaseHas('logs_systeme', [
            'action' => 'alerte_gerante_annulation_depot',
            'module' => 'reservations',
        ]);
    }

    public function test_acompte_paye_vers_absence_sans_raison_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'acompte_paye']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'absence',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['raison']);
    }

    // ─── Transitions non autorisees ──────────────────────────────────────────

    public function test_terminee_vers_en_cours_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'terminee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertStatus(422);
    }

    public function test_annulee_vers_confirmee_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'annulee']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertStatus(422);
    }

    public function test_en_attente_vers_terminee_refuse_422(): void
    {
        $auth = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        // en_attente n a pas acces direct a terminee dans les transitions gerante.
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'terminee',
            ])
            ->assertStatus(422);
    }

    // ─── Log de base sur transition normale ──────────────────────────────────

    public function test_changement_normal_cree_un_log_systeme(): void
    {
        $auth        = $this->loggedInGerante();
        $reservation = Reservation::factory()->create(['statut' => 'en_attente']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/gerante/reservations/{$reservation->id}/statut", [
                'statut' => 'confirmee',
            ])
            ->assertOk();

        $this->assertDatabaseHas('logs_systeme', [
            'action' => 'gerante_changement_statut',
            'module' => 'reservations',
        ]);
    }
}
