<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ===== FORCER JSON POUR TOUTES LES REQUÊTES API =====
        if ($request->is('api/*') || $request->wantsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $response = $next($request);

        // ===== CONVERTIR HTML EN JSON POUR LES ERREURS API =====
        if (($request->is('api/*') || $request->wantsJson()) && $response->status() >= 400) {
            $contentType = $response->headers->get('Content-Type', '');
            
            // Si c'est du HTML ou du texte, convertir en JSON
            if (str_contains($contentType, 'text/html') || 
                str_contains($contentType, 'text/plain') || 
                !str_contains($contentType, 'application/json')) {
                
                $content = $response->getContent();
                
                return response()->json([
                    'success' => false,
                    'message' => $this->extractErrorMessage($content),
                    'status' => $response->status(),
                    'error_type' => match($response->status()) {
                        404 => 'not_found',
                        403 => 'forbidden',
                        422 => 'validation_error',
                        500 => 'server_error',
                        default => 'error'
                    }
                ], $response->status());
            }
        }

        return $response;
    }

    /**
     * Extraire le message d'erreur du HTML
     */
    private function extractErrorMessage(string $html): string
    {
        // Chercher le titre d'erreur (h1, h2, ou message d'exception)
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/Exception.*?:\s*([^<\n]+)/', $html, $matches)) {
            return trim($matches[1]);
        }

        // Chercher "SQLSTATE" pour les erreurs DB
        if (preg_match('/SQLSTATE\[(\w+)\]:\s*([^<\n]+)/i', $html, $matches)) {
            return "Erreur base de données: " . trim($matches[2]);
        }

        return 'Une erreur est survenue';
    }
}
