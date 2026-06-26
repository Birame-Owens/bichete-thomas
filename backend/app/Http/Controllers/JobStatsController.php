<?php

namespace App\Http\Controllers;

use App\Services\EmailJobQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pour voir les statistiques des emails et messages
 */
class JobStatsController extends Controller
{
    /**
     * Afficher les stats des emails
     */
    public function emailStats(): JsonResponse
    {
        $stats = [
            'queue' => DB::table('email_job_queues')
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray(),
            'total_queue' => DB::table('email_job_queues')->count(),
            'sent_today' => DB::table('email_job_queues')
                ->where('status', 'sent')
                ->whereDate('sent_at', today())
                ->count(),
            'failed_today' => DB::table('email_job_queues')
                ->where('status', 'failed')
                ->whereDate('updated_at', today())
                ->count(),
        ];
        
        return response()->json($stats);
    }
    
    /**
     * Afficher les stats WhatsApp
     */
    public function whatsappStats(): JsonResponse
    {
        $stats = [
            'messages' => DB::table('messages_whatsapp')
                ->select('statut', DB::raw('count(*) as total'))
                ->groupBy('statut')
                ->get()
                ->pluck('total', 'statut')
                ->toArray(),
            'total' => DB::table('messages_whatsapp')->count(),
            'sent_today' => DB::table('messages_whatsapp')
                ->where('statut', 'envoye')
                ->whereDate('date_envoi', today())
                ->count(),
            'delivered_today' => DB::table('messages_whatsapp')
                ->where('statut', 'delivered')
                ->whereDate('date_envoi', today())
                ->count(),
        ];
        
        return response()->json($stats);
    }
    
    /**
     * Afficher les stats des trackers (open/click)
     */
    public function trackerStats(): JsonResponse
    {
        $stats = [
            'total_sent' => DB::table('email_trackers')->count(),
            'total_opened' => DB::table('email_trackers')->where('opened_at', '!=', null)->count(),
            'total_clicked' => DB::table('email_trackers')->where('clicked_at', '!=', null)->count(),
            'open_rate' => $this->calculateOpenRate(),
            'click_rate' => $this->calculateClickRate(),
            'recent_opens' => DB::table('email_trackers')
                ->whereNotNull('opened_at')
                ->orderBy('opened_at', 'desc')
                ->limit(10)
                ->get(),
        ];
        
        return response()->json($stats);
    }
    
    /**
     * Tableau de bord complet
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'emails' => $this->emailStats()->getData(true),
            'whatsapp' => $this->whatsappStats()->getData(true),
            'trackers' => $this->trackerStats()->getData(true),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Récupérer les emails échoués pour debug
     */
    public function failedEmails(): JsonResponse
    {
        $failed = DB::table('email_job_queues')
            ->where('status', 'failed')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();
        
        return response()->json([
            'total' => count($failed),
            'emails' => $failed,
        ]);
    }
    
    /**
     * Calculer le taux d'ouverture
     */
    private function calculateOpenRate(): float
    {
        $total = DB::table('email_trackers')->count();
        $opened = DB::table('email_trackers')->whereNotNull('opened_at')->count();
        
        return $total > 0 ? round(($opened / $total) * 100, 2) : 0;
    }
    
    /**
     * Calculer le taux de clic
     */
    private function calculateClickRate(): float
    {
        $total = DB::table('email_trackers')->count();
        $clicked = DB::table('email_trackers')->whereNotNull('clicked_at')->count();
        
        return $total > 0 ? round(($clicked / $total) * 100, 2) : 0;
    }
}
