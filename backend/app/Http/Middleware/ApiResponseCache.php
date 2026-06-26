<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiResponseCache
{
    /**
     * Routes à mettre en cache (GET uniquement)
     */
    private array $cacheableRoutes = [
        'api/client/products' => 3600,            // 1h
        'api/client/categories' => 86400,         // 24h
        'api/client/home' => 1800,                // 30min
        'api/client/navigation' => 43200,         // 12h
        'api/client/config' => 86400,             // 24h
        // Widgets page d'accueil — fortement sollicités, données peu fraîches
        'api/client/featured-products' => 1800,   // 30min
        'api/client/new-arrivals' => 1800,        // 30min
        'api/client/products-on-sale' => 1800,    // 30min
        'api/client/categories-preview' => 1800,  // 30min
        'api/client/active-promotions' => 600,    // 10min (promos plus volatiles)
        'api/client/shop-stats' => 1800,          // 30min
        'api/client/testimonials' => 3600,        // 1h
        'api/client/delivery-zones' => 86400,     // 24h
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Seulement pour les requêtes GET
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Vérifier si la route est cacheable
        $routePath = $request->path();
        $ttl = $this->getCacheTtl($routePath);

        if (!$ttl) {
            return $next($request);
        }

        // Générer clé de cache incluant query params
        $cacheKey = $this->generateCacheKey($request);

        try {
            // Tenter de récupérer depuis le cache
            $cachedResponse = Cache::tags(['api_responses'])->get($cacheKey);

            if ($cachedResponse) {
                Log::debug('API Response Cache HIT', ['key' => $cacheKey]);
                
                return response()->json($cachedResponse)
                    ->header('X-Cache', 'HIT')
                    ->header('X-Cache-Key', $cacheKey);
            }

            // Exécuter la requête
            $response = $next($request);

            // Mettre en cache si réponse JSON et statut 200
            if ($response->isSuccessful() && str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
                $responseData = json_decode($response->getContent(), true);
                
                if ($responseData && isset($responseData['success']) && $responseData['success']) {
                    Cache::tags(['api_responses'])->put($cacheKey, $responseData, $ttl);
                    
                    Log::debug('API Response Cache MISS - Stored', [
                        'key' => $cacheKey,
                        'ttl' => $ttl,
                    ]);

                    $response->header('X-Cache', 'MISS');
                }
            }

            return $response->header('X-Cache-Key', $cacheKey);

        } catch (\Exception $e) {
            Log::error('API Response Cache Error', [
                'error' => $e->getMessage(),
                'route' => $routePath,
            ]);

            // En cas d'erreur, continuer sans cache
            return $next($request);
        }
    }

    /**
     * Obtenir le TTL pour une route
     */
    private function getCacheTtl(string $routePath): ?int
    {
        foreach ($this->cacheableRoutes as $pattern => $ttl) {
            if (str_starts_with($routePath, $pattern)) {
                return $ttl;
            }
        }

        return null;
    }

    /**
     * Générer une clé de cache unique
     */
    private function generateCacheKey(Request $request): string
    {
        $path = $request->path();
        $query = $request->query();
        
        // Trier les params pour cohérence
        ksort($query);
        
        $queryString = http_build_query($query);
        
        return 'api:' . md5($path . ':' . $queryString);
    }
}
