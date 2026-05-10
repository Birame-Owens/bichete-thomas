<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Tests d integration sur le rate limiting (B3 + B9).
 *
 * Avec throttle:5,1 sur POST /auth/login, la 6e tentative depuis la meme
 * IP/cle dans la fenetre de 1 minute doit retourner 429.
 */
class ThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Vide le cache de rate limit entre tests pour eviter les interferences.
        RateLimiter::clear($this->throttleKey());
    }

    public function test_login_brute_force_est_bloque_apres_5_tentatives(): void
    {
        // 5 tentatives autorisees (toutes echouent puisque pas de user).
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'fake@test.local',
                'password' => 'wrong',
            ]);
            $this->assertSame(401, $response->status(), "Tentative #{$i} aurait du retourner 401, recu {$response->status()}");
        }

        // 6e tentative -> 429 par le middleware throttle:5,1.
        $response = $this->postJson('/api/auth/login', [
            'email' => 'fake@test.local',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    /**
     * La cle utilisee par Laravel throttle middleware pour le bucket de
     * notre user de test (memo : c est l IP par defaut, ici 127.0.0.1
     * + un signature route).
     */
    private function throttleKey(): string
    {
        return sha1('127.0.0.1');
    }
}
