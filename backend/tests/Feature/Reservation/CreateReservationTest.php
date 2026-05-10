<?php

namespace Tests\Feature\Reservation;

use App\Models\CategorieCoiffure;
use App\Models\Coiffure;
use App\Models\ParametreSysteme;
use App\Models\Reservation;
use App\Models\VarianteCoiffure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests d integration sur POST /api/client/reservations (B9).
 *
 * Ce flux est le plus critique de l app : creation client + reservation +
 * paiement pending + validation capacite + advisory lock B2 (skip en sqlite).
 *
 * Couvre :
 * - succes avec donnees minimales
 * - validation : champs obligatoires, mode_paiement, etc.
 * - jour ferme -> 422
 * - capacite journaliere atteinte -> 422
 * - capacite creneau atteinte -> 422
 */
class CreateReservationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSystemSettings();
        $this->configurePaymentGateways();
    }

    /**
     * Mock les APIs externes Stripe / PayTech : on ne veut pas que les tests
     * fassent de vrais appels HTTP. On simule des reponses success.
     */
    private function configurePaymentGateways(): void
    {
        // Config minimum pour que les controllers ne refusent pas le mode_paiement.
        config([
            'services.paytech.api_key' => 'test-key',
            'services.paytech.api_secret' => 'test-secret',
            'services.paytech.base_url' => 'https://paytech.sn/api',
            'services.paytech.env' => 'test',
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.currency' => 'xof',
        ]);

        // Mock les reponses HTTP que les controllers vont attendre.
        Http::fake([
            'https://paytech.sn/*' => Http::response([
                'success' => 1,
                'token' => 'paytech-fake-token',
                'redirect_url' => 'https://paytech.sn/checkout/fake',
            ], 200),
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_fake',
                'url' => 'https://checkout.stripe.com/fake',
            ], 200),
        ]);
    }

    public function test_un_client_peut_creer_une_reservation_avec_paiement_wave(): void
    {
        $coiffure = $this->createCoiffure();
        $variante = $coiffure->variantes->first();

        // Date demain a une heure couverte par les horaires (10:00).
        $tomorrow = Carbon::tomorrow()->toDateString();

        $response = $this->postJson('/api/client/reservations', [
            'client' => [
                'nom' => 'Diop',
                'prenom' => 'Awa',
                'telephone' => '+221771234567',
                'email' => 'awa@test.local',
            ],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $variante->id,
            'option_ids' => [],
            'date_reservation' => $tomorrow,
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'data' => ['id', 'client_id', 'date_reservation', 'heure_debut', 'heure_fin', 'statut'],
            'payment',
        ]);
        $response->assertJsonPath('data.statut', 'en_attente');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('paiements', 1);
        $this->assertDatabaseHas('paiements', ['statut' => 'en_attente', 'mode_paiement' => 'wave']);
    }

    public function test_validation_refuse_les_champs_manquants(): void
    {
        $response = $this->postJson('/api/client/reservations', [
            'client' => ['nom' => '', 'prenom' => '', 'telephone' => ''],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'client.nom',
            'client.prenom',
            'client.telephone',
            'coiffure_id',
            'variante_coiffure_id',
            'date_reservation',
            'heure_debut',
            'mode_paiement',
        ]);
    }

    public function test_validation_refuse_un_mode_paiement_inconnu(): void
    {
        $coiffure = $this->createCoiffure();

        $response = $this->postJson('/api/client/reservations', [
            'client' => ['nom' => 'X', 'prenom' => 'Y', 'telephone' => '+221770000000'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => Carbon::tomorrow()->toDateString(),
            'heure_debut' => '10:00',
            'mode_paiement' => 'paypal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['mode_paiement']);
    }

    public function test_validation_refuse_une_date_dans_le_passe(): void
    {
        $coiffure = $this->createCoiffure();

        $response = $this->postJson('/api/client/reservations', [
            'client' => ['nom' => 'X', 'prenom' => 'Y', 'telephone' => '+221770000000'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => Carbon::yesterday()->toDateString(),
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_reservation']);
    }

    public function test_jour_ferme_refuse_la_reservation(): void
    {
        // Configure le dimanche comme jour de fermeture.
        ParametreSysteme::query()->updateOrCreate(
            ['cle' => 'jours_fermeture'],
            ['valeur' => ['value' => ['dimanche']], 'type' => 'json', 'description' => 'Jours fermes', 'modifiable' => true],
        );

        $coiffure = $this->createCoiffure();

        // Trouve le prochain dimanche.
        $sunday = Carbon::now();
        while ($sunday->dayOfWeekIso !== 7) {
            $sunday->addDay();
        }

        $response = $this->postJson('/api/client/reservations', [
            'client' => ['nom' => 'X', 'prenom' => 'Y', 'telephone' => '+221770000000'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => $sunday->toDateString(),
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date_reservation']);
    }

    public function test_capacite_journaliere_atteinte_refuse(): void
    {
        // Limite journaliere = 1 -> on remplit avec une resa, la 2e doit echouer.
        // Utilise ->first()->update() pour declencher l event "saved" qui
        // flush le cache SystemSettings (sinon le query-builder update ne le
        // fait pas et le cache rend l ancienne valeur).
        ParametreSysteme::query()->where('cle', 'limite_reservations_par_jour')->first()->update([
            'valeur' => ['value' => 1],
        ]);

        $coiffure = $this->createCoiffure();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $payload = [
            'client' => ['nom' => 'A', 'prenom' => 'A', 'telephone' => '+221770000001'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => $tomorrow,
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ];

        // 1ere resa OK
        $this->postJson('/api/client/reservations', $payload)->assertStatus(201);

        // 2e resa (autre client, autre creneau) doit echouer : limite jour atteinte.
        $payload['client']['telephone'] = '+221770000002';
        $payload['heure_debut'] = '11:00';
        $r = $this->postJson('/api/client/reservations', $payload);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['date_reservation']);
    }

    public function test_capacite_creneau_atteinte_refuse(): void
    {
        // SQLite gere mal whereTime sur des valeurs sans secondes (le SQL
        // strftime('%H:%M:%S', '10:00') retourne NULL). Ce test n est donc
        // pertinent que sur Postgres -> il tournera sur le CI qui utilise
        // Postgres, on le skip en local sqlite.
        if (\DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('whereTime non fiable sur SQLite — testé en CI Postgres uniquement.');
        }

        // Limite par creneau = 1, mais limite jour OK.
        // ->first()->update() pour fire le saved event qui invalide le cache.
        ParametreSysteme::query()->where('cle', 'limite_reservations_par_creneau')->first()->update([
            'valeur' => ['value' => 1],
        ]);

        $coiffure = $this->createCoiffure();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $payload = [
            'client' => ['nom' => 'A', 'prenom' => 'A', 'telephone' => '+221770000001'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => $tomorrow,
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ];

        // 1re resa sur le creneau 10:00 OK
        $this->postJson('/api/client/reservations', $payload)->assertStatus(201);

        // 2e resa, autre client, MEME creneau 10:00 -> doit echouer.
        $payload['client']['telephone'] = '+221770000002';
        $r = $this->postJson('/api/client/reservations', $payload);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['heure_debut']);
    }

    public function test_meme_telephone_reutilise_le_client_existant(): void
    {
        $coiffure = $this->createCoiffure();
        $tomorrow = Carbon::tomorrow()->toDateString();

        $payload = [
            'client' => ['nom' => 'Diop', 'prenom' => 'Awa', 'telephone' => '+221771234567'],
            'coiffure_id' => $coiffure->id,
            'variante_coiffure_id' => $coiffure->variantes->first()->id,
            'date_reservation' => $tomorrow,
            'heure_debut' => '10:00',
            'mode_paiement' => 'wave',
        ];

        $this->postJson('/api/client/reservations', $payload)->assertStatus(201);

        $payload['heure_debut'] = '11:00';
        $this->postJson('/api/client/reservations', $payload)->assertStatus(201);

        // Toujours 1 client en base (reutilise par tel + nom + prenom).
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('reservations', 2);
    }

    // -----------------------------------------------------------------
    // Helpers de seed pour les tests
    // -----------------------------------------------------------------

    private function seedSystemSettings(): void
    {
        $defaults = [
            ['cle' => 'heure_ouverture', 'valeur' => ['value' => '09:00'], 'type' => 'time'],
            ['cle' => 'heure_fermeture', 'valeur' => ['value' => '19:00'], 'type' => 'time'],
            ['cle' => 'limite_reservations_par_jour', 'valeur' => ['value' => 15], 'type' => 'integer'],
            ['cle' => 'limite_reservations_par_creneau', 'valeur' => ['value' => 3], 'type' => 'integer'],
            ['cle' => 'pourcentage_acompte', 'valeur' => ['value' => 30], 'type' => 'decimal'],
            ['cle' => 'montant_acompte_defaut', 'valeur' => ['value' => 5000], 'type' => 'decimal'],
        ];

        foreach ($defaults as $setting) {
            ParametreSysteme::query()->updateOrCreate(
                ['cle' => $setting['cle']],
                array_merge($setting, ['description' => 'test', 'modifiable' => true]),
            );
        }
    }

    private function createCoiffure(): Coiffure
    {
        $cat = CategorieCoiffure::query()->create([
            'nom' => 'Tresses',
            'actif' => true,
        ]);

        $coiffure = Coiffure::query()->create([
            'categorie_coiffure_id' => $cat->id,
            'nom' => 'Tresses africaines',
            'description' => 'Test',
            'actif' => true,
        ]);

        VarianteCoiffure::query()->create([
            'coiffure_id' => $coiffure->id,
            'nom' => 'Standard',
            'prix' => 15000,
            'duree_minutes' => 60,
            'actif' => true,
        ]);

        return $coiffure->fresh(['variantes', 'options']);
    }
}
