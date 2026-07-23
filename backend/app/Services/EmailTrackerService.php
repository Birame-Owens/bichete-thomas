<?php

namespace App\Services;

use App\Models\EmailTracker;
use Illuminate\Support\Facades\Log;

/**
 * Service pour tracker les interactions avec les emails
 * (ouvertures, clics, bounces)
 */
class EmailTrackerService
{
    /**
     * Enregistrer l'ouverture d'un email (via pixel de tracking)
     */
    public static function trackOpen(string $token, string $ipAddress, string $userAgent)
    {
        try {
            $tracker = EmailTracker::where('token', $token)->first();
            
            if (!$tracker) {
                Log::warning('Token de tracking non trouvé', ['token' => $token]);
                return false;
            }
            
            if (!$tracker->opened_at) {
                $tracker->update([
                    'opened_at' => now(),
                    'pixel_loaded' => true,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);
                
                Log::info('Email ouvert', ['email' => $tracker->email]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur tracking open', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Enregistrer un clic dans un email
     */
    public static function trackClick(string $token)
    {
        try {
            $tracker = EmailTracker::where('token', $token)->first();
            
            if (!$tracker) {
                return false;
            }
            
            $tracker->update([
                'clicked_at' => now(),
                'click_count' => $tracker->click_count + 1,
            ]);
            
            Log::info('Email cliqué', ['email' => $tracker->email, 'clicks' => $tracker->click_count]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur tracking click', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Enregistrer un bounce (rebond)
     */
    public static function trackBounce(string $email, string $reason = 'unknown')
    {
        try {
            $tracker = EmailTracker::where('email', $email)->latest()->first();
            
            if ($tracker && !$tracker->bounced_at) {
                $tracker->update([
                    'bounced_at' => now(),
                    'bounce_reason' => $reason,
                ]);
                
                Log::warning('Email rebondeur', ['email' => $email, 'reason' => $reason]);
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Erreur tracking bounce', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Obtenir les stats de tracking
     */
    public static function getStats()
    {
        $totalSent = EmailTracker::count();
        $opened = EmailTracker::whereNotNull('opened_at')->count();
        $clicked = EmailTracker::whereNotNull('clicked_at')->count();
        $bounced = EmailTracker::whereNotNull('bounced_at')->count();
        
        return [
            'total_sent' => $totalSent,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => $bounced,
            'open_rate' => $totalSent > 0 ? round(($opened / $totalSent) * 100, 2) : 0,
            'click_rate' => $totalSent > 0 ? round(($clicked / $totalSent) * 100, 2) : 0,
            'bounce_rate' => $totalSent > 0 ? round(($bounced / $totalSent) * 100, 2) : 0,
        ];
    }
}
