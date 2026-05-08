<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cookieToken = $request->cookie(AuthController::AUTH_COOKIE);
        $bearerToken = $request->bearerToken();
        $plainTextToken = $cookieToken ?: $bearerToken;

        if (! $plainTextToken) {
            return response()->json([
                'message' => 'Token absent.',
            ], 401);
        }

        if ($cookieToken && in_array($request->method(), self::MUTATING_METHODS, true)) {
            $csrfCookie = (string) $request->cookie(AuthController::CSRF_COOKIE);
            $csrfHeader = (string) $request->header('X-XSRF-TOKEN');

            if ($csrfCookie === '' || $csrfHeader === '' || ! hash_equals($csrfCookie, $csrfHeader)) {
                return response()->json([
                    'message' => 'CSRF token mismatch.',
                ], 419);
            }
        }

        $accessToken = PersonalAccessToken::query()
            ->with('user.role')
            ->where('token', hash('sha256', $plainTextToken))
            ->first();

        if (! $accessToken || ($accessToken->expires_at && $accessToken->expires_at->isPast())) {
            return response()->json([
                'message' => 'Token invalide ou expire.',
            ], 401);
        }

        if (! $accessToken->user->actif) {
            return response()->json([
                'message' => 'Compte desactive.',
            ], 403);
        }

        if (! $accessToken->user->hasRole('admin', 'gerante')) {
            return response()->json([
                'message' => 'Acces reserve aux administrateurs et gerantes.',
            ], 403);
        }

        if ($this->shouldRefreshLastUsedAt($accessToken)) {
            $accessToken->forceFill(['last_used_at' => now()])->save();
        }

        Auth::setUser($accessToken->user);
        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('access_token', $accessToken);

        return $next($request);
    }

    private function shouldRefreshLastUsedAt(PersonalAccessToken $accessToken): bool
    {
        return $accessToken->last_used_at === null
            || $accessToken->last_used_at->lt(now()->subMinutes(5));
    }
}
