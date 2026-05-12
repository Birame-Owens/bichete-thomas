<?php

namespace Tests\Feature\Client;

use App\Console\Commands\DispatchReviewInvitations;
use App\Jobs\SendReviewInvitation;
use App\Models\AvisCoiffure;
use App\Models\CategorieCoiffure;
use App\Models\Client;
use App\Models\Coiffure;
use App\Models\DetailReservation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Tests d integration Phase 5 etape 3 : avis verifies post-prestation.
 *
 * Couvre :
 * - GET /avis/{token} : prefill valide, token invalide, token expire
 * - POST /avis/{token} : creation avis verifie, double soumission, token invalide,
 *   token expire, validation note/commentaire, token consomme apres soumission
 * - Command reviews:dispatch-invitations : dispatche les jobs pour les
 *   reservations eligibles, ignore les deja invitees
 */
class AvisVerifieTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // GET /api/client/avis/{token} — prefill
    // -----------------------------------------------------------------

    public function test_prefill_retourne_prenom_et_coiffure(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation);

        $response = $this->getJson("/api/client/avis/{$rawToken}");

        $response->assertOk();
        $response->assertJsonPath('data.prenom', $reservation->client->prenom);
        $response->assertJsonPath('data.coiffure_nom', 'Tresses box braids');
    }

    public function test_prefill_token_inconnu_retourne_404(): void
    {
        $response = $this->getJson('/api/client/avis/' . str_repeat('x', 64));

        $response->assertStatus(404);
    }

    public function test_prefill_token_expire_retourne_404(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation, expiredAt: true);

        $response = $this->getJson("/api/client/avis/{$rawToken}");

        $response->assertStatus(404);
    }

    // -----------------------------------------------------------------
    // POST /api/client/avis/{token} — soumission
    // -----------------------------------------------------------------

    public function test_store_cree_un_avis_verifie_et_consomme_le_token(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation);

        $response = $this->postJson("/api/client/avis/{$rawToken}", [
            'note' => 5,
            'commentaire' => 'Tres bonne prestation, je reviendrai.',
        ]);

        $response->assertStatus(201);

        // Avis cree avec verifie=true.
        $avis = AvisCoiffure::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($avis);
        $this->assertTrue($avis->verifie);
        $this->assertSame('en_attente', $avis->statut);
        $this->assertSame(5, $avis->note);

        // Token consomme en DB.
        $this->assertNull($reservation->fresh()->review_token);
    }

    public function test_store_token_inconnu_retourne_422(): void
    {
        $response = $this->postJson('/api/client/avis/' . str_repeat('x', 64), [
            'note' => 4,
            'commentaire' => 'Super prestation du salon.',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_token_expire_retourne_422(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation, expiredAt: true);

        $response = $this->postJson("/api/client/avis/{$rawToken}", [
            'note' => 4,
            'commentaire' => 'Super prestation du salon.',
        ]);

        $response->assertStatus(422);
    }

    public function test_double_soumission_retourne_422(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation);

        // Premier avis.
        $this->postJson("/api/client/avis/{$rawToken}", [
            'note' => 5,
            'commentaire' => 'Tres bonne prestation, je reviendrai.',
        ])->assertStatus(201);

        // Deuxieme tentative avec un nouveau token (cas ou quelqu un essaie de
        // soumettre un second avis verifie sur la meme reservation).
        $rawToken2 = $this->setReviewToken($reservation);
        $response = $this->postJson("/api/client/avis/{$rawToken2}", [
            'note' => 1,
            'commentaire' => 'Finalement pas si bien que ca.',
        ]);

        $response->assertStatus(422);
    }

    public function test_validation_note_et_commentaire(): void
    {
        $reservation = $this->createTermineeReservation();
        $rawToken = $this->setReviewToken($reservation);

        // Note hors intervalle.
        $this->postJson("/api/client/avis/{$rawToken}", [
            'note' => 6,
            'commentaire' => 'Ok',
        ])->assertStatus(422)->assertJsonValidationErrors(['note']);

        // Commentaire trop court.
        $this->postJson("/api/client/avis/{$rawToken}", [
            'note' => 4,
            'commentaire' => 'Court',
        ])->assertStatus(422)->assertJsonValidationErrors(['commentaire']);
    }

    // -----------------------------------------------------------------
    // Command reviews:dispatch-invitations
    // -----------------------------------------------------------------

    public function test_command_dispatche_les_jobs_pour_reservations_eligibles(): void
    {
        Bus::fake();

        // Eligible : terminee, date hier, pas encore invitee.
        $eligible = $this->createTermineeReservation(daysAgo: 1);

        // Non eligible : terminee mais deja invitee.
        $dejaInvitee = $this->createTermineeReservation(daysAgo: 2);
        $dejaInvitee->forceFill(['review_invited_at' => now()])->save();

        // Non eligible : pas encore terminee.
        $enAttente = $this->createTermineeReservation(daysAgo: 1);
        $enAttente->forceFill(['statut' => 'confirmee'])->save();

        $this->artisan(DispatchReviewInvitations::class)->assertSuccessful();

        Bus::assertDispatched(SendReviewInvitation::class, fn ($job) => $job->reservationId === $eligible->id);
        Bus::assertNotDispatched(SendReviewInvitation::class, fn ($job) => $job->reservationId === $dejaInvitee->id);
        Bus::assertNotDispatched(SendReviewInvitation::class, fn ($job) => $job->reservationId === $enAttente->id);
    }

    public function test_command_dry_run_ne_dispatche_rien(): void
    {
        Bus::fake();

        $this->createTermineeReservation(daysAgo: 1);

        $this->artisan(DispatchReviewInvitations::class, ['--dry-run' => true])->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private static int $seq = 0;

    private function createTermineeReservation(int $daysAgo = 1): Reservation
    {
        $categorie = CategorieCoiffure::query()->firstOrCreate(
            ['nom' => 'Tresses'],
            ['actif' => true],
        );

        $coiffure = Coiffure::query()->firstOrCreate(
            ['categorie_coiffure_id' => $categorie->id, 'nom' => 'Tresses box braids'],
            ['actif' => true],
        );

        $telephone = '+221770' . str_pad(++self::$seq, 6, '0', STR_PAD_LEFT);

        $client = Client::query()->create([
            'nom' => 'Diallo',
            'prenom' => 'Fatou',
            'telephone' => $telephone,
            'source' => 'en_ligne',
            'est_blackliste' => false,
        ]);

        $reservation = Reservation::query()->create([
            'client_id' => $client->id,
            'date_reservation' => now()->subDays($daysAgo)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '12:00',
            'duree_totale_minutes' => 120,
            'statut' => 'terminee',
            'source' => 'en_ligne',
            'montant_total' => 20000,
            'montant_acompte' => 5000,
            'montant_reduction' => 0,
            'montant_restant' => 15000,
            'devise' => 'FCFA',
        ]);

        DetailReservation::query()->create([
            'reservation_id' => $reservation->id,
            'coiffure_id' => $coiffure->id,
            'coiffure_nom' => 'Tresses box braids',
            'variante_nom' => 'Petites tresses',
            'prix_unitaire' => 20000,
            'duree_minutes' => 120,
            'quantite' => 1,
            'montant_options' => 0,
            'montant_total' => 20000,
            'ordre' => 1,
        ]);

        return $reservation->load(['client', 'details']);
    }

    /**
     * Pose un review_token sur la reservation et retourne le token brut.
     */
    private function setReviewToken(Reservation $reservation, bool $expiredAt = false): string
    {
        $raw = str_repeat('t', 64);
        $reservation->forceFill([
            'review_token' => hash('sha256', $raw),
            'review_token_expires_at' => $expiredAt ? now()->subMinute() : now()->addDays(7),
            'review_invited_at' => now(),
        ])->save();

        return $raw;
    }
}
