<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware (utilisé côté client routes).
 * Les routes admin utilisent RateLimitMiddleware via l'alias 'throttle.api'.
 * Les headers de sécurité sont gérés par SecurityHeaders.php.
 * Le CORS est géré par HandleCors (config/cors.php).
 */
class RateLimitingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/auth/login', 'api/auth/register')) {
            return $this->limitByIp($request, $next, 'login', 5, 60);
        }

        if ($request->is('api/*') && auth()->check()) {
            return $this->limitByUser($request, $next, 'api', 60, 60);
        }

        if ($request->is('api/products/search')) {
            return $this->limitByUser($request, $next, 'search', 30, 60);
        }

        if ($request->is('api/payments/*')) {
            return $this->limitByUser($request, $next, 'payment', 10, 60);
        }

        return $next($request);
    }

    protected function limitByIp(Request $request, Closure $next, string $key, int $limit, int $decay): Response
    {
        $limiter = "ip:{$key}:" . $request->ip();

        if (RateLimiter::tooManyAttempts($limiter, $limit)) {
            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Réessayez dans ' . RateLimiter::availableIn($limiter) . ' secondes.',
                'code' => 'RATE_LIMIT',
            ], 429);
        }

        RateLimiter::hit($limiter, $decay);

        return $next($request)->header('X-RateLimit-Remaining', RateLimiter::remaining($limiter, $limit));
    }

    protected function limitByUser(Request $request, Closure $next, string $key, int $limit, int $decay): Response
    {
        $userId = auth()->id() ?? $request->ip();
        $limiter = "user:{$key}:{$userId}";

        if (RateLimiter::tooManyAttempts($limiter, $limit)) {
            return response()->json([
                'success' => false,
                'message' => 'Limite de requêtes dépassée. Réessayez dans ' . RateLimiter::availableIn($limiter) . ' secondes.',
                'code' => 'RATE_LIMIT',
            ], 429);
        }

        RateLimiter::hit($limiter, $decay);

        return $next($request)
            ->header('X-RateLimit-Limit', $limit)
            ->header('X-RateLimit-Remaining', RateLimiter::remaining($limiter, $limit))
            ->header('X-RateLimit-Reset', RateLimiter::resetAfter($limiter));
    }
}
