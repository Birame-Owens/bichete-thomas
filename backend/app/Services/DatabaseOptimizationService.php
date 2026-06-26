<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Service d'optimisation des requêtes database
 * 
 * Prévient le problème N+1 et optimise les performances
 */
class DatabaseOptimizationService
{
    /**
     * Eager loading automatique pour les relations
     */
    public static function eagerLoadRelations(Builder $query, array $relations): Builder
    {
        return $query->with($relations);
    }

    /**
     * Paginé avec eager loading
     */
    public static function paginateWithRelations(Builder $query, array $relations, $perPage = 15)
    {
        return $query
            ->with($relations)
            ->paginate($perPage);
    }

    /**
     * Compter les requêtes DB (debug)
     */
    public static function debugQueries(callable $callback)
    {
        DB::enableQueryLog();
        
        $callback();
        
        $queries = DB::getQueryLog();
        return [
            'count' => count($queries),
            'queries' => $queries,
        ];
    }

    /**
     * Optimiser les requêtes Product
     */
    public static function optimizeProductQueries($query)
    {
        return $query
            ->with(['category', 'images', 'promotions'])
            ->select([
                'id', 'name', 'slug', 'description', 'price', 
                'discount_price', 'stock', 'category_id', 'created_at'
            ]);
    }

    /**
     * Optimiser les requêtes Command
     */
    public static function optimizeCommandQueries($query)
    {
        return $query
            ->with(['client', 'items.product', 'payment'])
            ->select([
                'id', 'client_id', 'total', 'status', 'created_at'
            ]);
    }

    /**
     * Optimiser les requêtes Client
     */
    public static function optimizeClientQueries($query)
    {
        return $query
            ->with(['addresses', 'wishlist'])
            ->select([
                'id', 'name', 'email', 'phone', 'created_at'
            ]);
    }

    /**
     * Chunk processing pour grandes listes (évite mémoire)
     */
    public static function chunkProcess($model, $callback, $chunkSize = 1000)
    {
        $model::chunk($chunkSize, function ($items) use ($callback) {
            foreach ($items as $item) {
                $callback($item);
            }
        });
    }

    /**
     * Cache les résultats de requête
     */
    public static function withCache(callable $query, $key, $minutes = 60)
    {
        return \Cache::remember($key, $minutes * 60, $query);
    }

    /**
     * Invalider le cache
     */
    public static function invalidateCache($pattern)
    {
        // À implémenter selon votre stratégie de cache
        \Cache::forget($pattern);
    }

    /**
     * Analyser les slow queries en PostgreSQL
     * Utilise l'extension pg_stat_statements (si disponible)
     */
    public static function analyzeSlowQueries()
    {
        try {
            // Vérifier si l'extension pg_stat_statements est chargée
            $extensionExists = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'pg_stat_statements'");
            
            if (empty($extensionExists)) {
                return [
                    'error' => 'Extension pg_stat_statements non disponible',
                    'solution' => 'Exécuter en tant que superuser: CREATE EXTENSION IF NOT EXISTS pg_stat_statements;'
                ];
            }
            
            // Récupérer les 10 requêtes les plus lentes
            return DB::select("
                SELECT 
                    query,
                    calls as nb_appels,
                    ROUND(total_exec_time::numeric, 2) as temps_total_ms,
                    ROUND(mean_exec_time::numeric, 2) as temps_moyen_ms,
                    ROUND(max_exec_time::numeric, 2) as temps_max_ms,
                    rows,
                    100.0 * shared_blks_hit / nullif(shared_blks_hit + shared_blks_read, 0) as cache_hit_ratio
                FROM pg_stat_statements
                WHERE query NOT LIKE '%pg_stat_statements%'
                ORDER BY mean_exec_time DESC
                LIMIT 10
            ");
        } catch (\Exception $e) {
            // Fallback : retourner les logs Laravel des queries lentes
            return [
                'error' => 'Impossible d\'accéder à pg_stat_statements',
                'message' => $e->getMessage(),
                'fallback' => 'Consultez les logs de performance : storage/logs/performance-*.log'
            ];
        }
    }
}
