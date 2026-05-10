<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\AuthController;
use App\Models\PersonalAccessToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests d integration sur le flux de login (B9).
 *
 * Couvre les comportements critiques de POST /api/auth/login :
 * - succes : 200 + cookies httpOnly poses + token cree en base
 * - mauvais mot de passe : 401
 * - compte desactive : 403
 * - role non autorise : 403
 * - validation : 422 sur input invalide
 */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_peut_se_logger_avec_les_bons_identifiants(): void
    {
        $user = $this->createAdminUser('admin@test.local', 'StrongPass2026!');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'StrongPass2026!',
        ]);

        $response->assertOk();
        // Le JSON ne doit PAS contenir le token (B4 : il vit dans le cookie httpOnly).
        $response->assertJsonMissing(['access_token']);
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'role'],
        ]);
        $response->assertJsonPath('user.email', 'admin@test.local');
        $response->assertJsonPath('user.role', 'admin');

        // 2 cookies poses : auth_token (httpOnly) et XSRF-TOKEN (lisible JS).
        $cookies = $response->headers->getCookies();
        $names = collect($cookies)->map(fn ($c) => $c->getName())->all();
        $this->assertContains(AuthController::AUTH_COOKIE, $names);
        $this->assertContains(AuthController::CSRF_COOKIE, $names);

        $authCookie = collect($cookies)->first(fn ($c) => $c->getName() === AuthController::AUTH_COOKIE);
        $this->assertTrue($authCookie->isHttpOnly(), 'Le cookie auth_token doit etre HttpOnly (anti-XSS B4).');

        $csrfCookie = collect($cookies)->first(fn ($c) => $c->getName() === AuthController::CSRF_COOKIE);
        $this->assertFalse($csrfCookie->isHttpOnly(), 'Le cookie XSRF-TOKEN doit etre lisible par JS (axios echo).');

        // Token persiste en base avec last_used_at = now() (anchor pour I1 inactivite).
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $token = PersonalAccessToken::query()->first();
        $this->assertEquals($user->id, $token->user_id);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_login_refuse_un_mauvais_mot_de_passe(): void
    {
        $this->createAdminUser('admin@test.local', 'StrongPass2026!');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Identifiants incorrects.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_refuse_un_email_inexistant(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'inconnu@test.local',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Identifiants incorrects.');
    }

    public function test_login_refuse_un_compte_desactive(): void
    {
        $user = $this->createAdminUser('admin@test.local', 'StrongPass2026!');
        $user->update(['actif' => false]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.local',
            'password' => 'StrongPass2026!',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Compte desactive.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_refuse_un_role_non_admin_ni_gerante(): void
    {
        // Cree un user avec un role qui n est ni admin ni gerante.
        $randomRole = Role::query()->create(['nom' => 'visiteur', 'description' => '']);
        $user = User::query()->create([
            'role_id' => $randomRole->id,
            'name' => 'Test',
            'email' => 'visiteur@test.local',
            'password' => Hash::make('StrongPass2026!'),
            'actif' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'StrongPass2026!',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Acces reserve aux administrateurs et gerantes.');
    }

    public function test_login_valide_le_format_des_donnees(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'pas-un-email',
            'password' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }
}
