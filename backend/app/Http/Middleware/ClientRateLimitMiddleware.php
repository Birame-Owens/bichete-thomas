<?php
// ================================================================
// 📝 FICHIER: app/Http/Middleware/ClientRateLimitMiddleware.php
// ================================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ClientRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '100', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        // Vérifier le rate limiting
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $this->logRateLimitExceeded($request, $key);
            
            return response()->json([
                'success' => false,
                'message' => 'Trop de requêtes. Veuillez patienter quelques instants.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        // Incrémenter le compteur
        RateLimiter::hit($key, $decayMinutes * 60);

        // Analytics et logging pour les clients
        $this->trackClientActivity($request);

        // Continuer avec la requête
        $response = $next($request);

        // Ajouter des headers de sécurité et cache pour les clients
        return $this->addSecurityHeaders($response);
    }

    /**
     * Résoudre la signature de la requête pour le rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = $request->user()?->id;
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Si utilisateur authentifié, utiliser son ID, sinon IP + User Agent
        if ($userId) {
            return "client_user_{$userId}";
        }
        
        return 'client_guest_' . sha1($ip . '|' . $userAgent);
    }

    /**
     * Logger l'activité client pour analytics
     */
    protected function trackClientActivity(Request $request): void
    {
        // Ne logger que si nécessaire (pas toutes les requêtes)
        $shouldTrack = $this->shouldTrackRequest($request);
        
        if ($shouldTrack) {
            $data = [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'referer' => $request->header('Referer'),
                'timestamp' => now()->toISOString(),
                'session_id' => session()->getId()
            ];

            // Logger de manière asynchrone pour ne pas ralentir la réponse
            Log::channel('client_analytics')->info('Client activity', $data);

            // Mettre à jour les stats en cache (pour dashboard admin)
            $this->updateClientStats($request);
        }
    }

    /**
     * Déterminer si on doit tracker cette requête
     */
    protected function shouldTrackRequest(Request $request): bool
    {
        $trackableRoutes = [
            'client/home',
            'client/featured-products', 
            'client/new-arrivals',
            'client/products-on-sale',
            'client/search/quick',
            'client/newsletter/subscribe'
        ];

        $path = $request->path();
        
        foreach ($trackableRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mettre à jour les statistiques client en cache
     */
    protected function updateClientStats(Request $request): void
    {
        $today = now()->format('Y-m-d');
        $hour = now()->format('H');
        
        // Incrémenter les compteurs
        Cache::increment("client_visits_{$today}", 1);
        Cache::increment("client_visits_{$today}_{$hour}", 1);
        
        // Tracker les pages populaires
        $path = $request->path();
        Cache::increment("client_page_views_{$path}_{$today}", 1);
        
        // Tracker les appareils
        $isMobile = $this->isMobileDevice($request->userAgent());
        $deviceType = $isMobile ? 'mobile' : 'desktop';
        Cache::increment("client_device_{$deviceType}_{$today}", 1);
    }

    /**
     * Détecter si c'est un appareil mobile
     */
    protected function isMobileDevice(string $userAgent): bool
    {
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'iPod',
            'BlackBerry', 'Windows Phone', 'Opera Mini'
        ];
        
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Ajouter des headers de sécurité et cache
     */
    protected function addSecurityHeaders(Response $response): Response
    {
        // Headers de sécurité
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Headers de cache pour optimiser les performances client
        if ($this->isCacheableRequest()) {
            $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
            $response->headers->set('Vary', 'Accept-Encoding');
        } else {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // Header personnalisé pour identifier les réponses API client
        $response->headers->set('X-API-Type', 'NDEYA-CLIENT');
        $response->headers->set('X-API-Version', '1.0');

        return $response;
    }

    /**
     * Déterminer si la requête peut être mise en cache
     */
    protected function isCacheableRequest(): bool
    {
        $request = request();
        
        // Cacher seulement les GET requests pour certaines routes
        if ($request->method() !== 'GET') {
            return false;
        }

        $cacheableRoutes = [
            'client/home',
            'client/featured-products',
            'client/new-arrivals', 
            'client/products-on-sale',
            'client/categories-preview',
            'client/shop-stats'
        ];

        $path = $request->path();
        
        foreach ($cacheableRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Logger les tentatives de rate limit dépassées
     */
    protected function logRateLimitExceeded(Request $request, string $key): void
    {
        Log::warning('Client rate limit exceeded', [
            'key' => $key,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toISOString()
        ]);

        // Alerter l'admin si trop d'abus détectés
        $abusiveRequestsToday = Cache::get("rate_limit_exceeded_" . $request->ip() . "_" . now()->format('Y-m-d'), 0);
        
        if ($abusiveRequestsToday > 10) {
            Log::alert('Possible client API abuse detected', [
                'ip' => $request->ip(),
                'exceeded_count' => $abusiveRequestsToday,
                'user_agent' => $request->userAgent()
            ]);
        }
        
        Cache::increment("rate_limit_exceeded_" . $request->ip() . "_" . now()->format('Y-m-d'), 1);
    }
}