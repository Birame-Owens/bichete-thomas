<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Bypass custom rate limiter — prevents 429 when login tests run back-to-back
        $this->withoutMiddleware(\App\Http\Middleware\RateLimitMiddleware::class);
    }

    private function makeAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role'   => 'admin',
            'statut' => 'actif',
        ], $overrides));
    }

    // ── login ─────────────────────────────────────────────────────────────────

    public function test_login_returns_success_for_valid_admin(): void
    {
        $admin = $this->makeAdmin(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/admin/login', [
            'email'    => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['user']]);
    }

    public function test_login_returns_401_for_wrong_password(): void
    {
        $admin = $this->makeAdmin(['password' => bcrypt('correct')]);

        $this->postJson('/api/admin/login', [
            'email'    => $admin->email,
            'password' => 'wrongpassword',
        ])->assertStatus(401)
          ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    }

    public function test_login_returns_403_for_non_admin(): void
    {
        $user = User::factory()->create([
            'role'     => 'client',
            'statut'   => 'actif',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/admin/login', [
            'email'    => $user->email,
            'password' => 'secret123',
        ])->assertStatus(403)
          ->assertJsonPath('error_code', 'ACCESS_DENIED');
    }

    public function test_login_returns_403_for_suspended_account(): void
    {
        $admin = $this->makeAdmin([
            'statut'   => 'suspendu',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/admin/login', [
            'email'    => $admin->email,
            'password' => 'secret123',
        ])->assertStatus(403)
          ->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson('/api/admin/login', [])
             ->assertStatus(422)
             ->assertJsonPath('success', false);
    }

    // ── /api/admin/user ───────────────────────────────────────────────────────

    public function test_user_endpoint_returns_admin_info(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')
             ->getJson('/api/admin/user')
             ->assertStatus(200)
             ->assertJsonPath('success', true)
             ->assertJsonStructure(['data' => ['user']]);
    }

    public function test_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/admin/user')
             ->assertStatus(401);
    }

    // ── /api/admin/check ──────────────────────────────────────────────────────

    public function test_check_returns_authenticated_true_for_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'sanctum')
             ->getJson('/api/admin/check')
             ->assertStatus(200)
             ->assertJsonPath('authenticated', true);
    }

    public function test_check_returns_401_when_guest(): void
    {
        // The /check route is under auth:sanctum — unauthenticated requests get 401
        $this->getJson('/api/admin/check')
             ->assertStatus(401);
    }

    // ── logout ────────────────────────────────────────────────────────────────

    public function test_logout_succeeds_without_active_session(): void
    {
        // Logout does not require auth — it clears whatever session/guard is active.
        // Using actingAs('sanctum') would switch the default guard to Sanctum's
        // RequestGuard which lacks a logout() method, causing a 500. We test the
        // unauthenticated path: the endpoint is public (throttle-only), and when
        // no user is logged in it still returns 200 successfully.
        $this->postJson('/api/admin/logout')
             ->assertStatus(200)
             ->assertJsonPath('success', true);
    }
}
