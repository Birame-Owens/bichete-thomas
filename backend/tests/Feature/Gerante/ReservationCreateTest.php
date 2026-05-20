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
 * Tests d integration sur POST /api/gerante/reservations.
 *
 * Couvre :
 * - acces sans token refuse en 401
 * - acces admin refuse en 403
 * - creation avec acompte : paiement type=acompte, statut=acompte_paye
 * - creation soldee : paiement type=complet, statut=en_cours
 * - acompte refuse si montant calcule est 0 (validation 422)
 * - mode_paiement requis (champ obligatoire dans les deux cas)
 * - client blackliste refuse en 422
 * - coiffure inactive refuse en 422
 * - log cree a la creation
 * - admin peut passer acompte_paye en en_cours
 * - gerante doit fournir raison pour annuler une resa en_cours (soldee)
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
     * Payload minimal valide. type_paiement et mode_paiement sont desormais
     * obligatoires : la gerante doit toujours encaisser a la creation.
     *
     * @return array<string, mixed>
     */
    private function payload(int $clientId, int $coiffureId, int $varianteId): array
    {
        return [
            'client_id'        => $clientId,
            'date_reservation' => now()->addDay()->toDateString(),
            'heure_debut'      => '10:00',
            'type_paiement'    => 'acompte',
            'mode_paiement'    => 'especes',
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

    // ─── Creation avec acompte ────────────────────────────────────────────────

    public function test_creation_acompte_cree_paiement_et_passe_en_acompte_paye(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante([], ['prix' => 20000]);
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'pourcentage_acompte'],
            ['valeur' => ['value' => 40], 'type' => 'integer'],
        );

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(201);

        $response->assertJsonPath('data.statut', 'acompte_paye');
        $response->assertJsonPath('data.source', 'physique');

        $reservationId = $response->json('data.id');

        $this->assertDatabaseHas('paiements', [
            'reservation_id' => $reservationId,
            'type'           => 'acompte',
            'statut'         => 'valide',
            'mode_paiement'  => 'especes',
            'montant'        => 8000, // 40 % de 20 000
        ]);

        $paiement = Paiement::where('reservation_id', $reservationId)->first();
        $this->assertStringStartsWith('BT-', $paiement->numero_recu);
    }

    // ─── Creation soldee ─────────────────────────────────────────────────────

    public function test_creation_soldee_cree_paiement_complet_et_passe_en_cours(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante([], ['prix' => 25000]);
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $payload                 = $this->payload($client->id, $coiffure->id, $variante->id);
        $payload['type_paiement'] = 'soldee';
        $payload['mode_paiement'] = 'wave';

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $payload)
            ->assertStatus(201);

        $response->assertJsonPath('data.statut', 'en_cours');

        $reservationId = $response->json('data.id');

        $this->assertDatabaseHas('paiements', [
            'reservation_id' => $reservationId,
            'type'           => 'complet',
            'statut'         => 'valide',
            'mode_paiement'  => 'wave',
            'montant'        => 25000,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id'              => $reservationId,
            'montant_restant' => 0,
        ]);
    }

    // ─── Validations ─────────────────────────────────────────────────────────

    public function test_acompte_refuse_si_montant_calcule_est_zero(): void
    {
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

        // type_paiement=acompte avec deposit=0 doit etre refuse clairement
        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $this->payload($client->id, $coiffure->id, $variante->id))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type_paiement']);
    }

    public function test_mode_paiement_requis(): void
    {
        ['coiffure' => $coiffure, 'variante' => $variante] = $this->coiffureAvecVariante();
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $payload = $this->payload($client->id, $coiffure->id, $variante->id);
        unset($payload['mode_paiement']);

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/reservations', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode_paiement']);
    }

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

    // ─── Logs ────────────────────────────────────────────────────────────────

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

    // ─── Transitions sensibles ────────────────────────────────────────────────

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

    public function test_gerante_doit_fournir_raison_pour_annuler_resa_en_cours(): void
    {
        $reservation = Reservation::factory()->create(['statut' => 'en_cours']);
        $auth        = $this->loggedInGerante();

        // Sans raison : refuse
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
}
