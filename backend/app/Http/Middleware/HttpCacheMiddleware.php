<?php
/**
 * ⚡ MIDDLEWARE DE CACHING HTTP - RÉDUIRE CHARGE SERVEUR
 * - Cache les réponses GET
 * - Headers ETag + Last-Modified
 * - Support 304 Not Modified
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HttpCacheMiddleware
{
    /**
     * 🎯 Durées de cache par endpoint
     */
    private const CACHE_DURATIONS = [
        'categories' => 600,           // 10 minutes
        'products' => 300,             // 5 minutes
        'products/trending' => 1800,   // 30 minutes
        'search' => 120,               // 2 minutes
        'config' => 1800,              // 30 minutes
        'navigation' => 1800,          // 30 minutes
    ];

    /**
     * 🔐 Endpoints à ne PAS cacher
     */
    private const NO_CACHE_PATTERNS = [
        'auth',
        'cart',
        'wishlist',
        'profile',
        'orders',
        'checkout',
        'payment',
    ];

    public function handle(Request $request, Closure $next)
    {
        // ✅ 1️⃣ VÉRIFIER SI CACHABLE
        if (!$this->isCacheable($request)) {
            return $next($request);
        }

        // ✅ 2️⃣ CLÉS DE CACHE
        $cacheKey = $this->generateCacheKey($request);
        $etagKey = "etag_{$cacheKey}";

        // ✅ 3️⃣ VÉRIFIER SI-NON-MODIFIÉ (304)
        $clientEtag = $request->getETags();
        $serverEtag = Cache::get($etagKey);

        if ($clientEtag && $serverEtag && in_array($serverEtag, $clientEtag)) {
            return response('', 304)
                ->header('ETag', $serverEtag)
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // ✅ 4️⃣ VÉRIFIER CACHE
        $cached = Cache::get($cacheKey);
        if ($cached) {
            Log::debug('✅ Cache HIT', ['endpoint' => $request->path()]);
            
            return response($cached['content'], 200)
                ->header('Content-Type', $cached['content_type'])
                ->header('ETag', $cached['etag'])
                ->header('X-From-Cache', 'true')
                ->header('Cache-Control', 'public, max-age=' . $cached['max_age']);
        }

        // ✅ 5️⃣ EXÉCUTER REQUÊTE
        $response = $next($request);

        // ✅ 6️⃣ CACHER RÉPONSE (si 200 OK)
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($response, $cacheKey, $etagKey);
        }

        return $response;
    }

    /**
     * 🔍 Vérifier si endpoint est cacheable
     */
    private function isCacheable(Request $request): bool
    {
        // ✅ Seulement GET requests
        if ($request->getMethod() !== 'GET') {
            return false;
        }

        // ✅ Vérifier patterns à exclure
        foreach (self::NO_CACHE_PATTERNS as $pattern) {
            if (str_contains($request->path(), $pattern)) {
                return false;
            }
        }

        // ✅ Ne pas cacher si ?nocache=1
        if ($request->has('nocache')) {
            return false;
        }

        return true;
    }

    /**
     * 🔑 Générer clé de cache avec paramètres
     */
    private function generateCacheKey(Request $request): string
    {
        $path = $request->path();
        $query = $request->query();
        
        // ✅ Exclure certains paramètres
        $filtered = collect($query)
            ->reject(fn($v, $k) => in_array($k, ['_t', 'nocache']))
            ->all();

        $params = empty($filtered) ? '' : '_' . md5(json_encode($filtered));
        
        return "cache_api_{$path}{$params}";
    }

    /**
     * 💾 Cacher la réponse
     */
    private function cacheResponse($response, string $cacheKey, string $etagKey): void
    {
        try {
            $duration = $this->getCacheDuration($cacheKey);
            $etag = md5($response->getContent());

            $cacheData = [
                'content' => $response->getContent(),
                'content_type' => $response->headers->get('Content-Type'),
                'etag' => $etag,
                'max_age' => $duration,
            ];

            Cache::put($cacheKey, $cacheData, $duration);
            Cache::put($etagKey, $etag, $duration);

            // ✅ Ajouter headers
            $response->header('ETag', $etag);
            $response->header('Cache-Control', "public, max-age={$duration}");
            $response->header('X-Cache-Duration', $duration);

        } catch (\Exception $e) {
            Log::error('Cache storage failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ⏱️ Obtenir durée de cache selon endpoint
     */
    private function getCacheDuration(string $cacheKey): int
    {
        foreach (self::CACHE_DURATIONS as $pattern => $duration) {
            if (str_contains($cacheKey, $pattern)) {
                return $duration;
            }
        }

        return 300; // Défaut: 5 min
    }
}
