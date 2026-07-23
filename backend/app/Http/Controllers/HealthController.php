<?php

namespace App\Http\Controllers;

use App\Services\MonitoringService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Health check endpoint
     * 
     * @return JsonResponse
     */
    public function check(): JsonResponse
    {
        $health = MonitoringService::getSystemHealth();
        
        // Déterminer le statut global
        $isHealthy = collect($health)->every(fn($status) => $status === true || $status > 0);
        
        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'system_health' => $health,
        ], $isHealthy ? 200 : 503);
    }
    
    /**
     * Retourner les stats de base
     * 
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'timestamp' => now()->toIso8601String(),
            'app_name' => config('app.name'),
            'app_version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'driver' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
            ],
        ]);
    }
}
