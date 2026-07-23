<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\MonitoringService;

class MonitoringMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $responseTime = (microtime(true) - $startTime) * 1000; // milliseconds
        
        // Log l'API request
        MonitoringService::logApiRequest(
            $request,
            $responseTime,
            $response->getStatusCode()
        );
        
        // Ajouter les headers de monitoring
        $response->header('X-Response-Time', round($responseTime, 2) . 'ms');
        $response->header('X-Monitoring-Enabled', 'true');
        
        return $response;
    }
}
