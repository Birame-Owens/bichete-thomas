<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tracker pour enregistrer les opens/clicks d'emails
 * Utilise des pixels invisibles et des liens trackés
 */
class EmailTrackerController extends Controller
{
    /**
     * Pixel de tracking (1x1 gif invisible)
     * GET /api/tracker/pixel/{token}
     */
    public function trackOpen($token): Response
    {
        try {
            // Récupérer le token de tracking
            $tracker = DB::table('email_trackers')
                ->where('open_token', $token)
                ->first();
            
            if (!$tracker) {
                // Retourner un pixel vide même si pas trouvé
                return $this->getBlankPixel();
            }
            
            // Mettre à jour le timestamp d'ouverture
            DB::table('email_trackers')
                ->where('id', $tracker->id)
                ->update([
                    'opened_at' => now(),
                    'open_ip' => request()->ip(),
                    'open_user_agent' => request()->header('User-Agent'),
                ]);
            
            Log::info('Email ouvert', [
                'tracker_id' => $tracker->id,
                'email' => $tracker->email,
                'ip' => request()->ip(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur tracking open', ['error' => $e->getMessage()]);
        }
        
        return $this->getBlankPixel();
    }
    
    /**
     * Tracker les clics sur les liens
     * GET /api/tracker/click/{token}?url={encoded_url}
     */
    public function trackClick($token): Response
    {
        try {
            $url = request()->query('url');
            
            if (!$url) {
                return response()->json(['error' => 'URL missing'], 400);
            }
            
            // Décoder l'URL
            $decodedUrl = base64_decode($url);
            
            // Récupérer le token
            $tracker = DB::table('email_trackers')
                ->where('click_token', $token)
                ->first();
            
            if (!$tracker) {
                return redirect($decodedUrl);
            }
            
            // Enregistrer le clic
            DB::table('email_trackers')
                ->where('id', $tracker->id)
                ->update([
                    'clicked_at' => now(),
                    'click_ip' => request()->ip(),
                    'click_url' => $decodedUrl,
                ]);
            
            Log::info('Email clic enregistré', [
                'tracker_id' => $tracker->id,
                'email' => $tracker->email,
                'url' => $decodedUrl,
            ]);
            
            // Rediriger vers l'URL réelle
            return redirect($decodedUrl);
            
        } catch (\Exception $e) {
            Log::error('Erreur tracking click', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error'], 500);
        }
    }
    
    /**
     * Retourner un pixel transparent 1x1
     */
    private function getBlankPixel(): Response
    {
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        
        return response($pixel)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }
}
