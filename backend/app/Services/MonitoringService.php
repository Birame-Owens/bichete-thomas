<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class MonitoringService
{
    /**
     * Enregistrer les requêtes API
     */
    public static function logApiRequest(Request $request, $responseTime, $statusCode)
    {
        Log::channel('api')->info('API Request', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
            'user_id' => auth()->id() ?? 'guest',
            'status_code' => $statusCode,
            'response_time_ms' => round($responseTime * 1000, 2),
            'timestamp' => now(),
        ]);
    }

    /**
     * Enregistrer les erreurs
     */
    public static function logError($message, $exception = null, $context = [])
    {
        Log::channel('errors')->error($message, array_merge([
            'exception' => $exception ? get_class($exception) : null,
            'trace' => $exception ? $exception->getTraceAsString() : null,
        ], $context));
    }

    /**
     * Enregistrer les performances DB
     */
    public static function logDatabaseQuery($query, $bindings, $time)
    {
        if ($time > 1000) { // Log seulement si > 1 seconde
            Log::channel('performance')->warning('Slow Database Query', [
                'query' => $query,
                'bindings' => $bindings,
                'time_ms' => $time,
                'timestamp' => now(),
            ]);
        }
    }

    /**
     * Enregistrer les actions critiques
     */
    public static function logAction($action, $user_id, $details = [])
    {
        Log::channel('actions')->info("Action: $action", array_merge([
            'user_id' => $user_id,
            'ip' => request()->ip(),
            'timestamp' => now(),
        ], $details));
    }

    /**
     * Vérifier la santé du système
     */
    public static function getSystemHealth(): array
    {
        return [
            'database' => self::checkDatabase(),
            'cache' => self::checkCache(),
            'disk' => self::checkDiskSpace(),
            'memory' => self::checkMemory(),
        ];
    }

    private static function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return ['status' => 'OK', 'message' => 'Database connected'];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private static function checkCache(): array
    {
        try {
            \Cache::put('health_check', 'ok', 60);
            \Cache::get('health_check');
            return ['status' => 'OK', 'message' => 'Cache working'];
        } catch (\Exception $e) {
            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }
    }

    private static function checkDiskSpace(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $percentage = round(($free / $total) * 100, 2);

        return [
            'status' => $percentage > 10 ? 'OK' : 'WARNING',
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'percentage_free' => $percentage,
        ];
    }

    private static function checkMemory(): array
    {
        $memory = memory_get_usage(true);
        $limit = ini_get('memory_limit');

        return [
            'current_mb' => round($memory / 1024 / 1024, 2),
            'limit' => $limit,
            'status' => 'OK',
        ];
    }
}
