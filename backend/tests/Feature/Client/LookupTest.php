<?php

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\PreferenceClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Tests d integration sur GET /api/client/lookup (Phase 5 etape 1).
 *
 * Couvre :
 *  - lookup found / not found / tel invalide ;
 *  - normalisation au passage (saisi avec espaces matche un E.164 en base) ;
 *  - privacy : aucun leak email / id / historique ;
 *  - throttle 5,1 (anti-annuaire-inverse).
 */
class LookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Vide le bucket throttle entre tests : sinon le test throttle ferme
        // la porte aux autres methodes qui font du lookup sur la meme IP.
        RateLimiter::clear(sha1('127.0.0.1'));
    }

    public function test_lookup_retourne_les_infos_d_un_client_existant(): void
    {
        Client::query()->create([
            'nom' => 'Diop',
            'prenom' => 'Awa',
            'telephone' => '+221771234567',
            'email' => 'awa.diop@example.com',
            'source' => 'physique',
        ]);

        $response = $this->getJson('/api/client/lookup?tel=%2B221771234567');

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'nom' => 'Diop',
            'prenom' => 'Awa',
        ]);
    }

    public function test_lookup_normalise_le_tel_en_entree(): void
    {
        Client::query()->create([
            'nom' => 'Ndiaye',
            'prenom' => 'Fatou',
            'telephone' => '+221770000001',
            'source' => 'physique',
        ]);

        // Saisie type formulaire : prefixe + espaces. Le helper doit normaliser
        // avant la query, sinon on louperait le client en base.
        $response = $this->getJson('/api/client/lookup?tel='.urlencode('+221 77 000 0001'));

        $response->assertOk();
        $response->assertJson(['found' => true, 'nom' => 'Ndiaye', 'prenom' => 'Fatou']);
    }

    public function test_lookup_accepte_un_numero_local_sans_prefixe(): void
    {
        Client::query()->create([
            'nom' => 'Sow',
            'prenom' => 'Ibrahima',
            'telephone' => '+221770000002',
            'source' => 'physique',
        ]);

        // Pas de + en prefixe : le default country SN doit prendre le relai.
        $response = $this->getJson('/api/client/lookup?tel=770000002');

        $response->assertOk();
        $response->assertJson(['found' => true, 'nom' => 'Sow']);
    }

    public function test_lookup_retourne_found_false_sur_tel_inconnu(): void
    {
        $response = $this->getJson('/api/client/lookup?tel=%2B221779999999');

        $response->assertOk();
        $response->assertExactJson([
            'found' => false,
            'nom' => null,
            'prenom' => null,
        ]);
    }

    public function test_lookup_retourne_found_false_sur_tel_invalide(): void
    {
        // Tel non parsable : on ne renvoie surtout pas 422 sinon on permet de
        // distinguer "valide inconnu" de "non parsable" (info-leak). 200 + found:false.
        $response = $this->getJson('/api/client/lookup?tel='.urlencode('voir avec elle'));

        $response->assertOk();
        $response->assertJson(['found' => false]);
    }

    public function test_lookup_refuse_l_appel_sans_param_tel(): void
    {
        $response = $this->getJson('/api/client/lookup');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tel']);
    }

    public function test_lookup_ne_leak_jamais_email_ni_id(): void
    {
        Client::query()->create([
            'nom' => 'Ba',
            'prenom' => 'Mamadou',
            'telephone' => '+221770000003',
            'email' => 'mamadou.ba@example.com',
            'source' => 'physique',
        ]);

        $response = $this->getJson('/api/client/lookup?tel=%2B221770000003');

        $response->assertOk();
        // Garde-fou : si quelqu un ajoute des champs au payload par erreur, ce
        // test casse vite. La privacy est la garantie principale de l endpoint.
        $response->assertJsonMissing(['email' => 'mamadou.ba@example.com']);
        $payload = $response->json();
        $this->assertSame(['found', 'nom', 'prenom'], array_keys($payload));
        $this->assertArrayNotHasKey('id', $payload);
        $this->assertArrayNotHasKey('telephone', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function test_lookup_throttle_apres_5_appels_par_minute(): void
    {
        // 5 lookups OK depuis la meme IP, le 6e doit etre 429.
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->getJson('/api/client/lookup?tel=%2B221770000004');
            $this->assertSame(200, $response->status(), "Lookup #{$i} aurait du etre 200, recu {$response->status()}");
        }

        $response = $this->getJson('/api/client/lookup?tel=%2B221770000004');
        $response->assertStatus(429);
    }

    public function test_lookup_ne_renvoie_pas_un_client_blackliste_avec_un_status_special(): void
    {
        // Decision metier : la blacklist est un mecanisme reservation (geree par
        // ClientResolver::findOrCreate au moment de creer une resa). Le lookup
        // public ne doit pas leaker ce status (c est une donnee interne).
        // Donc un client blackliste apparait comme found:true normal au lookup.
        Client::query()->create([
            'nom' => 'X',
            'prenom' => 'Y',
            'telephone' => '+221770000005',
            'est_blackliste' => true,
            'source' => 'physique',
        ]);

        $response = $this->getJson('/api/client/lookup?tel=%2B221770000005');

        $response->assertOk();
        $response->assertJson(['found' => true]);
        $payload = $response->json();
        $this->assertArrayNotHasKey('est_blackliste', $payload);
    }
}
