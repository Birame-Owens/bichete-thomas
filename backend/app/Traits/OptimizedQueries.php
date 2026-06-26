<?php

namespace App\Traits;

use App\Services\DatabaseOptimizationService;

/**
 * Trait pour optimiser les requêtes de base de données
 * Utilisé dans les contrôleurs pour éviter les N+1 queries
 */
trait OptimizedQueries
{
    /**
     * Get optimized products with relations
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    protected function getOptimizedProducts($query)
    {
        return DatabaseOptimizationService::optimizeProductQueries($query);
    }
    
    /**
     * Get optimized commands with relations
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    protected function getOptimizedCommands($query)
    {
        return DatabaseOptimizationService::optimizeCommandQueries($query);
    }
    
    /**
     * Get optimized clients with relations
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    protected function getOptimizedClients($query)
    {
        return DatabaseOptimizationService::optimizeClientQueries($query);
    }
    
    /**
     * Cache query results
     * 
     * @param callable $query
     * @param string $key
     * @param int $minutes
     * @return mixed
     */
    protected function getCachedResults(callable $query, string $key, int $minutes = 60)
    {
        return DatabaseOptimizationService::withCache($query, $key, $minutes);
    }
    
    /**
     * Process large datasets in chunks
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $chunkSize
     * @param callable $callback
     * @return void
     */
    protected function processInChunks($query, int $chunkSize, callable $callback)
    {
        DatabaseOptimizationService::chunkProcess($query, $chunkSize, $callback);
    }
    
    /**
     * Debug database queries count
     * 
     * @return int
     */
    protected function debugQueries()
    {
        return DatabaseOptimizationService::debugQueries();
    }
}
