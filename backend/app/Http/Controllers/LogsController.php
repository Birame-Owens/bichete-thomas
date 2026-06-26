<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class LogsController extends Controller
{
    /**
     * Récupérer les logs de performance
     * 
     * @return JsonResponse
     */
    public function performance(): JsonResponse
    {
        return $this->readLogs('performance');
    }
    
    /**
     * Récupérer les erreurs
     * 
     * @return JsonResponse
     */
    public function errors(): JsonResponse
    {
        return $this->readLogs('errors');
    }
    
    /**
     * Récupérer les requêtes API
     * 
     * @return JsonResponse
     */
    public function api(): JsonResponse
    {
        return $this->readLogs('api');
    }
    
    /**
     * Récupérer les actions critiques
     * 
     * @return JsonResponse
     */
    public function actions(): JsonResponse
    {
        return $this->readLogs('actions');
    }
    
    /**
     * Récupérer les logs de base de données
     * 
     * @return JsonResponse
     */
    public function database(): JsonResponse
    {
        return $this->readLogs('database');
    }
    
    /**
     * Récupérer les queries lentes
     * 
     * @return JsonResponse
     */
    public function slowQueries(): JsonResponse
    {
        $logPath = storage_path('logs/performance-' . now()->format('Y-m-d') . '.log');
        
        if (!file_exists($logPath)) {
            return response()->json([
                'data' => [],
                'message' => 'Pas de logs disponibles',
            ]);
        }
        
        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        
        $slowQueries = [];
        foreach ($lines as $line) {
            if (str_contains($line, 'SLOW QUERY')) {
                $slowQueries[] = $line;
            }
        }
        
        return response()->json([
            'data' => array_slice($slowQueries, -50), // Last 50
            'total' => count($slowQueries),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Lire les logs d'un canal spécifique
     * 
     * @param string $channel
     * @return JsonResponse
     */
    private function readLogs(string $channel): JsonResponse
    {
        $logPath = storage_path('logs/' . $channel . '-' . now()->format('Y-m-d') . '.log');
        
        if (!file_exists($logPath)) {
            return response()->json([
                'channel' => $channel,
                'data' => [],
                'message' => 'Pas de logs disponibles',
            ]);
        }
        
        $content = file_get_contents($logPath);
        $lines = explode("\n", array_filter(explode("\n", $content)));
        
        return response()->json([
            'channel' => $channel,
            'total' => count($lines),
            'data' => array_slice($lines, -100), // Last 100 lines
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
