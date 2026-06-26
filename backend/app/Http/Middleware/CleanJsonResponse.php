<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanJsonResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capturer toute sortie parasite
        ob_start();
        
        $response = $next($request);
        
        // Nettoyer le buffer de sortie
        $parasiteOutput = ob_get_clean();
        
        // Si c'est une réponse JSON et qu'il y a une sortie parasite
        if ($response->headers->get('Content-Type') === 'application/json' && !empty($parasiteOutput)) {
            // Log pour debug
            \Log::warning('Sortie parasite détectée: ' . $parasiteOutput);
        }
        
        return $response;
    }
}
