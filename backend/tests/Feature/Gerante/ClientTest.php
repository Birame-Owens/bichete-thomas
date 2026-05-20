<?php

namespace Tests\Feature\Gerante;

use App\Models\Client;
use App\Models\LogSysteme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d integration sur les routes GET/POST/PUT /api/gerante/clients.
 *
 * Couvre :
 * - acces sans token refuse en 401
 * - acces admin refuse en 403
 * - liste paginee et recherche
 * - creation d une cliente physique (source forcee)
 * - creation refuse si telephone invalide ou duplique
 * - modification des coordonnees autorisee
 * - modification refuse si telephone duplique
 * - champs interdits ignores a la creation (source, nombre_reservations_terminees, fidelite_disponible)
 * - champs interdits ignores a la modification (source, nombre_reservations_terminees, fidelite_disponible)
 * - log cree a la creation
 * - log cree a la modification avec before/after
 */
class ClientTest extends TestCase
{
    use RefreshDatabase;

    // ─── Acces ───────────────────────────────────────────────────────────────

    public function test_sans_token_liste_refuse_401(): void
    {
        $this->getJson('/api/gerante/clients')->assertStatus(401);
    }

    public function test_sans_token_creation_refuse_401(): void
    {
        $this->postJson('/api/gerante/clients', [])->assertStatus(401);
    }

    public function test_admin_ne_peut_pas_lister_clients_gerante_403(): void
    {
        $auth = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->getJson('/api/gerante/clients')
            ->assertStatus(403);
    }

    public function test_admin_ne_peut_pas_creer_client_gerante_403(): void
    {
        $auth = $this->loggedInAdmin();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom' => 'Test', 'prenom' => 'Test', 'telephone' => '+221771234567',
            ])
            ->assertStatus(403);
    }

    // ─── Liste ───────────────────────────────────────────────────────────────

    public function test_gerante_peut_lister_les_clientes(): void
    {
        Client::factory()->count(3)->create();

        $auth = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->getJson('/api/gerante/clients')
            ->assertOk();

        $response->assertJsonPath('data.total', 3);
        $response->assertJsonStructure(['data' => ['data', 'total', 'current_page', 'last_page']]);
    }

    /**
     * @group pgsql
     * ilike est specifique a PostgreSQL — ce test ne s execute qu en CI
     * (connexion pgsql). Sur SQLite il serait en erreur 500.
     */
    public function test_recherche_filtre_par_nom(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('ilike requiert PostgreSQL.');
        }

        Client::factory()->create(['nom' => 'Diallo', 'prenom' => 'Aissatou']);
        Client::factory()->create(['nom' => 'Ndiaye', 'prenom' => 'Fatou']);

        $auth = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->getJson('/api/gerante/clients?search=Diallo')
            ->assertOk();

        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.data.0.nom', 'Diallo');
    }

    /**
     * @group pgsql
     */
    public function test_recherche_filtre_par_telephone(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('ilike requiert PostgreSQL.');
        }

        Client::factory()->create(['telephone' => '+221771111111']);
        Client::factory()->create(['telephone' => '+221772222222']);

        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson('/api/gerante/clients?search=7711')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_gerante_peut_voir_une_cliente(): void
    {
        $client = Client::factory()->create();
        $auth   = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->getJson("/api/gerante/clients/{$client->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $client->id);
    }

    // ─── Creation ────────────────────────────────────────────────────────────

    public function test_creation_cliente_physique_reussie(): void
    {
        $auth = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Sarr',
                'prenom'    => 'Mariama',
                'telephone' => '+221771234560',
                'email'     => 'mariama@example.com',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Sarr')
            ->assertJsonPath('data.prenom', 'Mariama');

        // La source doit etre forcee a physique.
        $response->assertJsonPath('data.source', 'physique');

        $this->assertDatabaseHas('clients', [
            'nom'    => 'Sarr',
            'source' => 'physique',
        ]);
    }

    public function test_creation_cree_preferences_par_defaut(): void
    {
        $auth = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Sow',
                'prenom'    => 'Khady',
                'telephone' => '+221771234561',
            ])
            ->assertStatus(201);

        $clientId = $response->json('data.id');

        $this->assertDatabaseHas('preferences_clients', [
            'client_id'              => $clientId,
            'notifications_whatsapp' => true,
            'notifications_promos'   => true,
        ]);
    }

    public function test_source_forcee_a_physique_meme_si_en_ligne_envoye(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Ba',
                'prenom'    => 'Rokhaya',
                'telephone' => '+221771234562',
                'source'    => 'en_ligne',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.source', 'physique');
    }

    public function test_nombre_reservations_et_fidelite_ignores_a_la_creation(): void
    {
        $auth = $this->loggedInGerante();

        $response = $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'                          => 'Fall',
                'prenom'                       => 'Ndéye',
                'telephone'                    => '+221771234563',
                'nombre_reservations_terminees' => 50,
                'fidelite_disponible'           => true,
            ])
            ->assertStatus(201);

        $clientId = $response->json('data.id');
        $client   = Client::find($clientId);

        // Ces champs ne doivent pas etre modifies par la gerante.
        $this->assertEquals(0, $client->nombre_reservations_terminees);
        $this->assertFalse($client->fidelite_disponible);
    }

    public function test_creation_sans_nom_refuse_422(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'prenom'    => 'Mariama',
                'telephone' => '+221771234564',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nom']);
    }

    public function test_creation_telephone_invalide_refuse_422(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Test',
                'prenom'    => 'Test',
                'telephone' => '0612345678',  // pas en E.164 international
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telephone']);
    }

    public function test_creation_telephone_duplique_refuse_422(): void
    {
        Client::factory()->create(['telephone' => '+221771234565']);

        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Autre',
                'prenom'    => 'Autre',
                'telephone' => '+221771234565',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telephone']);
    }

    // ─── Modification ─────────────────────────────────────────────────────────

    public function test_gerante_peut_modifier_les_coordonnees(): void
    {
        $client = Client::factory()->create([
            'nom'       => 'Ancien',
            'prenom'    => 'Nom',
            'telephone' => '+221771234566',
        ]);

        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->putJson("/api/gerante/clients/{$client->id}", [
                'nom'    => 'Nouveau',
                'prenom' => 'Prenom',
            ])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau')
            ->assertJsonPath('data.prenom', 'Prenom');
    }

    public function test_modification_ne_peut_pas_changer_source(): void
    {
        $client = Client::factory()->create(['source' => 'physique']);
        $auth   = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->putJson("/api/gerante/clients/{$client->id}", [
                'source' => 'en_ligne',
            ])
            ->assertOk();

        // La source ne doit pas avoir change.
        $this->assertSame('physique', Client::find($client->id)->source);
    }

    public function test_modification_telephone_duplique_refuse_422(): void
    {
        $existing = Client::factory()->create(['telephone' => '+221771234567']);
        $target   = Client::factory()->create(['telephone' => '+221771234568']);

        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->putJson("/api/gerante/clients/{$target->id}", [
                'telephone' => $existing->telephone,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telephone']);
    }

    // ─── Logs ─────────────────────────────────────────────────────────────────

    public function test_creation_cree_un_log_systeme(): void
    {
        $auth = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->postJson('/api/gerante/clients', [
                'nom'       => 'Logtest',
                'prenom'    => 'Prenom',
                'telephone' => '+221771234569',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('logs_systeme', [
            'action' => 'gerante_creation_client',
            'module' => 'clients',
        ]);
    }

    public function test_modification_cree_un_log_avec_before_after(): void
    {
        $client = Client::factory()->create(['nom' => 'Avant', 'prenom' => 'Prenom']);
        $auth   = $this->loggedInGerante();

        $this->authenticatedAs($auth)
            ->withHeader('X-XSRF-TOKEN', $auth['csrf'])
            ->putJson("/api/gerante/clients/{$client->id}", ['nom' => 'Apres'])
            ->assertOk();

        $log = LogSysteme::where('action', 'gerante_modification_client')->first();

        $this->assertNotNull($log);
        $this->assertSame('Avant', $log->before['nom']);
        $this->assertSame('Apres', $log->after['nom']);
    }
}
