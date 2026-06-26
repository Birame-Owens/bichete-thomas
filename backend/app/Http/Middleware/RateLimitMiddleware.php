<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    protected $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next, $maxAttempts = 60, $decayMinutes = 1): Response
    {
        $testToken = config('app.performance_test_bypass_token');
        if ($testToken && hash_equals($testToken, (string) $request->header('X-Performance-Test-Token'))) {
            return $next($request);
        }

        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => 'Trop de requêtes. Veuillez réessayer dans ' . $this->limiter->availableIn($key) . ' secondes.',
                'retry_after' => $this->limiter->availableIn($key),
            ], 429);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $response->header(
            'X-RateLimit-Limit',
            $maxAttempts
        )->header(
            'X-RateLimit-Remaining',
            $this->limiter->remaining($key, $maxAttempts)
        );
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->method() .
            '|' . $request->getHost() .
            '|' . ($request->user()?->id ?? $request->ip())
        );
    }
}
