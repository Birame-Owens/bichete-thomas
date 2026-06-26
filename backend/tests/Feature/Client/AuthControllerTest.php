<?php

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\RateLimitMiddleware::class);
    }

    private function validRegisterPayload(array $overrides = []): array
    {
        return array_merge([
            'nom'                  => 'Diallo',
            'prenom'               => 'Fatou',
            'email'                => 'fatou.diallo@example.com',
            'telephone'            => '771234567',   // → +221771234567 after normalization
            'password'             => 'secret1234',
            'password_confirmation' => 'secret1234',
            'accepte_conditions'   => true,
        ], $overrides);
    }

    // ── POST /api/client/auth/register ───────────────────────────────────────

    public function test_register_creates_user_and_returns_201(): void
    {
        $response = $this->postJson('/api/client/auth/register', $this->validRegisterPayload());

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['email' => 'fatou.diallo@example.com', 'role' => 'client']);
    }

    public function test_register_requires_nom(): void
    {
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload(['nom' => '']))
             ->assertStatus(422)
             ->assertJsonPath('success', false);
    }

    public function test_register_requires_valid_email(): void
    {
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload(['email' => 'pas-un-email']))
             ->assertStatus(422);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'fatou.diallo@example.com', 'role' => 'client']);

        $this->postJson('/api/client/auth/register', $this->validRegisterPayload())
             ->assertStatus(422);
    }

    public function test_register_rejects_duplicate_phone(): void
    {
        // Create a client with the normalized phone so the unique check fires
        $user = User::factory()->create(['role' => 'client', 'statut' => 'actif']);
        Client::create([
            'nom'       => 'Autre',
            'prenom'    => 'Client',
            'telephone' => '+221771234567',   // normalized form stored in DB
            'user_id'   => $user->id,
            'type_client' => 'nouveau',
        ]);

        $this->postJson('/api/client/auth/register', $this->validRegisterPayload())
             ->assertStatus(422);
    }

    public function test_register_requires_password_confirmation(): void
    {
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload([
            'password_confirmation' => 'different_password',
        ]))->assertStatus(422);
    }

    public function test_register_requires_accepte_conditions(): void
    {
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload([
            'accepte_conditions' => false,
        ]))->assertStatus(422);
    }

    // ── POST /api/client/auth/login ──────────────────────────────────────────

    public function test_login_returns_token_for_valid_credentials(): void
    {
        // Register first so User + Client records both exist
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload());

        $response = $this->postJson('/api/client/auth/login', [
            'email'    => 'fatou.diallo@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_returns_401_for_wrong_password(): void
    {
        $this->postJson('/api/client/auth/register', $this->validRegisterPayload());

        $this->postJson('/api/client/auth/login', [
            'email'    => 'fatou.diallo@example.com',
            'password' => 'wrongpassword',
        ])->assertStatus(401);
    }

    public function test_login_returns_401_for_unknown_email(): void
    {
        $this->postJson('/api/client/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'secret1234',
        ])->assertStatus(401);
    }

    // ── GET /api/client/auth/profile ─────────────────────────────────────────

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/client/auth/profile')
             ->assertStatus(401);
    }

    public function test_profile_returns_data_when_authenticated(): void
    {
        $user = User::factory()->create(['role' => 'client', 'statut' => 'actif']);
        Client::create([
            'nom'        => 'Diallo',
            'prenom'     => 'Fatou',
            'telephone'  => '+221779999999',
            'user_id'    => $user->id,
            'type_client' => 'nouveau',
        ]);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/client/auth/profile')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }
}
