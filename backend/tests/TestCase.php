<?php

namespace Tests;

use App\Http\Controllers\Api\AuthController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cree un utilisateur admin actif. Helper utilise par la majorite des
     * tests qui ont besoin d une auth valide.
     */
    protected function createAdminUser(string $email = 'admin@test.local', string $password = 'TestAdmin2026!'): User
    {
        $role = Role::query()->firstOrCreate(
            ['nom' => 'admin'],
            ['description' => 'Administrateur (test)'],
        );

        return User::query()->create([
            'role_id' => $role->id,
            'name' => 'Test Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'actif' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Effectue un login complet et retourne la reponse.
     */
    protected function loginAs(User $user, string $password = 'TestAdmin2026!'): TestResponse
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);
    }

    /**
     * Extrait les cookies d auth + CSRF d une reponse de login.
     *
     * @return array<string, string>
     */
    protected function extractAuthCookies(TestResponse $loginResponse): array
    {
        $cookies = [];

        foreach ($loginResponse->headers->getCookies() as $cookie) {
            if (in_array($cookie->getName(), [AuthController::AUTH_COOKIE, AuthController::CSRF_COOKIE], true)) {
                $cookies[$cookie->getName()] = $cookie->getValue();
            }
        }

        return $cookies;
    }

    /**
     * Helper d auth pour les tests : login + retourne tout ce qu il faut
     * pour faire des requetes authentifiees.
     *
     * Note Laravel 13 : les cookies de test ne sont propages que si
     * withCredentials() est appele en amont. Le helper authenticatedAs()
     * ci-dessous gere ca pour toi.
     *
     * @return array{user: User, cookies: array<string, string>, csrf: string}
     */
    protected function loggedInAdmin(string $email = 'admin@test.local'): array
    {
        $user = $this->createAdminUser($email);
        $login = $this->loginAs($user);
        $login->assertOk();

        $cookies = $this->extractAuthCookies($login);

        return [
            'user' => $user,
            'cookies' => $cookies,
            'csrf' => $cookies[AuthController::CSRF_COOKIE] ?? '',
        ];
    }

    /**
     * Prepare le client de test avec l auth cookie httpOnly comme en prod.
     * A enchainer avec ->getJson() / ->postJson() / ... pour faire la requete.
     *
     * Usage typique :
     *   $auth = $this->loggedInAdmin();
     *   $this->authenticatedAs($auth)->getJson('/api/auth/me')->assertOk();
     *
     * Pour les mutations (POST/PUT/PATCH/DELETE) il faut aussi passer le
     * header X-XSRF-TOKEN, sinon CSRF rejette en 419 :
     *   $this->authenticatedAs($auth)
     *        ->withHeaders(['X-XSRF-TOKEN' => $auth['csrf']])
     *        ->postJson(...)
     *
     * @param array{cookies: array<string, string>, csrf: string} $auth
     */
    protected function authenticatedAs(array $auth): static
    {
        // withCredentials() est OBLIGATOIRE depuis Laravel 13 : sans, les
        // cookies sont strippes par prepareCookiesForBroadcastingRequest()
        // (defaut "no credentials, no cookies").
        return $this->withCredentials()->withUnencryptedCookies($auth['cookies']);
    }

    /**
     * Cree un utilisateur gerante actif.
     */
    protected function createGeranteUser(string $email = 'gerante@test.local', string $password = 'TestAdmin2026!'): User
    {
        $role = Role::query()->firstOrCreate(
            ['nom' => 'gerante'],
            ['description' => 'Gerante (test)'],
        );

        return User::query()->create([
            'role_id'            => $role->id,
            'name'               => 'Test Gerante',
            'email'              => $email,
            'password'           => Hash::make($password),
            'actif'              => true,
            'email_verified_at'  => now(),
        ]);
    }

    /**
     * Login complet en tant que gerante — meme interface que loggedInAdmin().
     *
     * @return array{user: User, cookies: array<string, string>, csrf: string}
     */
    protected function loggedInGerante(string $email = 'gerante@test.local'): array
    {
        $user  = $this->createGeranteUser($email);
        $login = $this->loginAs($user);
        $login->assertOk();

        $cookies = $this->extractAuthCookies($login);

        return [
            'user'    => $user,
            'cookies' => $cookies,
            'csrf'    => $cookies[AuthController::CSRF_COOKIE] ?? '',
        ];
    }
}
