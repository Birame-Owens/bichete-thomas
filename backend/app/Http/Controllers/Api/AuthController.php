<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthController extends Controller
{
    public const AUTH_COOKIE = 'auth_token';
    public const CSRF_COOKIE = 'XSRF-TOKEN';

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->with('role')
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Identifiants incorrects.',
            ], 401);
        }

        if (! $user->actif) {
            return response()->json([
                'message' => 'Compte desactive.',
            ], 403);
        }

        if (! $user->hasRole('admin', 'gerante')) {
            return response()->json([
                'message' => 'Acces reserve aux administrateurs et gerantes.',
            ], 403);
        }

        $token = $user->createApiToken($credentials['device_name'] ?? 'api');

        return response()
            ->json([
                'message' => 'Connexion reussie.',
                'user' => $this->serializeUser($user),
            ])
            ->withCookie($this->makeAuthCookie($token))
            ->withCookie($this->makeCsrfCookie());
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->serializeUser($request->user()->loadMissing('role')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('access_token');

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->withClearedAuthCookies(response()->json([
            'message' => 'Deconnexion reussie.',
        ]));
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->withClearedAuthCookies(response()->json([
            'message' => 'Toutes les sessions ont ete fermees.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->nom,
        ];
    }

    private function makeAuthCookie(string $token): SymfonyCookie
    {
        return cookie(
            name: self::AUTH_COOKIE,
            value: $token,
            minutes: (int) config('session.lifetime', 120),
            path: (string) config('session.path', '/'),
            domain: config('session.domain'),
            secure: (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: (string) config('session.same_site', 'lax'),
        );
    }

    private function makeCsrfCookie(): SymfonyCookie
    {
        return cookie(
            name: self::CSRF_COOKIE,
            value: Str::random(40),
            minutes: (int) config('session.lifetime', 120),
            path: (string) config('session.path', '/'),
            domain: config('session.domain'),
            secure: (bool) config('session.secure', false),
            httpOnly: false,
            raw: false,
            sameSite: (string) config('session.same_site', 'lax'),
        );
    }

    private function withClearedAuthCookies(JsonResponse $response): JsonResponse
    {
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');

        return $response
            ->withCookie(Cookie::forget(self::AUTH_COOKIE, $path, $domain))
            ->withCookie(Cookie::forget(self::CSRF_COOKIE, $path, $domain));
    }
}
