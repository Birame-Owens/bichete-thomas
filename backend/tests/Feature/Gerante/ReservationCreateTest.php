<?php

namespace Tests\Feature\Gerante;

use App\Models\Client;
use App\Models\Coiffure;
use App\Models\Paiement;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use App\Models\VarianteCoiffure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur POST /api/gerante/reservations et sur
 * la correction de la transition admin acompte_paye -> en_cours.
 *
 * Couvre :
 * - acces sans token refuse en 401
 * - acces admin refuse en 403
 * - creation d une reservation physique simple
 * - creation avec acompte : paiement cree + statut acompte_paye
 * - acompte ignore si montant calcule est 0
 * - client blackliste refuse en 422
 * - coiffure inactive refuse
 * - log cree a la creation
 * - admin peut maintenant passer acompte_paye en en_cours (fix)
 */
class ReservationCreateTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array{coiffure: Coiffure, variante: VarianteCoiffure}
     */
    private function coiffureAvecVariante(array $coiffureAttrs = [], array $varianteAttrs = []): array
    {
        $coiffure = Coiffure::factory()->create(['actif' => true, ...$coiffureAttrs]);
        $variante = VarianteCoiffure::factory()->create([
            'coiffure_id'   => $coiffure->id,
            'actif'         => true,
            'prix'          => 15000,
            'duree_minutes' => 60,
            ...$varianteAttrs,
        ]);

        return compact('coiffure', 'variante');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $clientId, int $coiffureId, int $varianteId): array
    {
        return [
            'client_id'        => $clientId,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_debut'      => '10:00',
            'details'          => [[
                'coiffure_id'          => $coiffureId,
                'variante_coiffure_id' => $varianteId,
                'quantite'             => 1,
            ]],
        ];
    }

    // ─── Acces ───────────────────────────────────────────────────────────────

    public function test_sans_token_creation_refuse_401(): void
    {
        $this->postJson('/api/gerante/reservations', [])->assertStatus(401);
    }

    public function test_admin_ne_peut_pas_creer_reservation_gerante_403(): void
    {
        $auth = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', [])
            ->assertStatus(403);
    }

    // ─── Creation simple ─────────────────────────────────────────────────────

    public function test_gerante_peut_creer_reservation_physique(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(201);

        $response->assertJsonPath('data.statut', 'en_attente');
        $response->assertJsonPath('data.source', 'physique');
        $response->assertJsonPath('data.client_id', $client->id);

        $this->assertDatabaseHas('reservations', [
            'client_id' => $client->id,
            'source'    => 'physique',
            'statut'    => 'en_attente',
        ]);
    }

    // ─── Creation avec acompte ────────────────────────────────────────────────

    public function test_creation_avec_acompte_cree_paiement_et_passe_en_acompte_paye(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante([], ['prix' => 20000]);
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        // Parametrage : 40 % d acompte (la migration cree deja cette cle — updateOrCreate)
        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'pourcentage_acompte'],
            ['valeur' => ['value' => 40], 'type' => 'integer'],
        );

        $payload = $this->payload($client->id, $coiffure->id, $variante->id);
        $payload['enregistrer_acompte']   = true;
        $payload['mode_paiement_acompte'] = 'especes';

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $payload)
            ->assertStatus(201);

        $response->assertJsonPath('data.statut', 'acompte_paye');

        $reservationId = $response->json('data.id');

        $this->assertDatabaseHas('paiements', [
            'reservation_id' => $reservationId,
            'type'           => 'acompte',
            'statut'         => 'valide',
            'mode_paiement'  => 'especes',
            'montant'        => 8000, // 40 % de 20 000
        ]);

        // numero_recu doit avoir ete remplace par le format BT-
        $paiement = Paiement::where('reservation_id', $reservationId)->first();
        $this->assertStringStartsWith('BT-', $paiement->numero_recu);
    }

    public function test_acompte_ignore_si_montant_calcule_est_zero(): void
    {
        // La migration cree des valeurs par defaut non nulles — on les force a 0
        // pour tester le cas ou aucun acompte n est configure.
        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'pourcentage_acompte'],
            ['valeur' => ['value' => 0]],
        );
        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'montant_acompte_defaut'],
            ['valeur' => ['value' => 0]],
        );

        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $payload = $this->payload($client->id, $coiffure->id, $variante->id);
        $payload['enregistrer_acompte']   = true;
        $payload['mode_paiement_acompte'] = 'wave';

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $payload)
            ->assertStatus(201);

        // Acompte calcule a 0 -> statut reste en_attente, aucun paiement cree
        $response->assertJsonPath('data.statut', 'en_attente');
        $this->assertDatabaseCount('paiements', 0);
    }

    public function test_mode_paiement_acompte_requis_si_enregistrer_acompte(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'pourcentage_acompte'],
            ['valeur' => ['value' => 40], 'type' => 'integer'],
        );

        $payload = $this->payload($client->id, $coiffure->id, $variante->id);
        $payload['enregistrer_acompte'] = true;
        // mode_paiement_acompte volontairement absent

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode_paiement_acompte']);
    }

    // ─── Validations ─────────────────────────────────────────────────────────

    public function test_client_blackliste_refuse_422(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create(['est_blackliste' => true]);
        $auth   = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_coiffure_inactive_refuse(): void
    {
        $coiffure = Coiffure::factory()->create(['actif' => false]);
        $variante = VarianteCoiffure::factory()->create(['coiffure_id' => $coiffure->id, 'actif' => true]);
        $client   = Client::factory()->create();
        $auth     = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(422);
    }

    // ─── Logs ─────────────────────────────────────────────────────────────────

    public function test_creation_cree_log_systeme(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(201);

        $this->assertDatabaseHas('logs_systeme', [
            'action' => 'gerante_creation_reservation',
            'module' => 'reservations',
        ]);
    }

    // ─── Fix transition admin : acompte_paye → en_cours ─────────────────────

    public function test_admin_peut_passer_acompte_paye_en_en_cours(): void
    {
        $reservation = Reservation::factory()->create(['statut' => 'acompte_paye']);
        $auth        = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->patchJson("/api/admin/reservations/{$reservation->id}/statut", [
                'statut' => 'en_cours',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_cours');
    }
}
