<?php

namespace App\Http\Controllers;

use App\Services\EmailJobQueueService;
use App\Services\EmailTrackerService;
use App\Services\WhatsAppWebhookService;
use App\Models\EmailJobQueue;
use App\Models\MessagesWhatsapp;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard pour voir les stats des emails et messages
 * Accessible en local pour tester
 */
class EmailStatsController extends Controller
{
    /**
     * Obtenir toutes les stats
     * GET /api/admin/email-stats
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'queue' => EmailJobQueueService::getStats(),
            'tracker' => EmailTrackerService::getStats(),
            'whatsapp' => WhatsAppWebhookService::getStats(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
    
    /**
     * Obtenir les stats de la file d'attente d'emails
     * GET /api/admin/email-stats/queue
     */
    public function queueStats(): JsonResponse
    {
        return response()->json(EmailJobQueueService::getStats());
    }
    
    /**
     * Obtenir les stats de tracking des emails
     * GET /api/admin/email-stats/tracker
     */
    public function trackerStats(): JsonResponse
    {
        return response()->json(EmailTrackerService::getStats());
    }
    
    /**
     * Obtenir les stats WhatsApp
     * GET /api/admin/email-stats/whatsapp
     */
    public function whatsappStats(): JsonResponse
    {
        return response()->json(WhatsAppWebhookService::getStats());
    }
    
    /**
     * Lister les emails en attente
     * GET /api/admin/email-stats/pending
     */
    public function pendingEmails(): JsonResponse
    {
        $emails = EmailJobQueue::where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get(['id', 'email', 'subject', 'status', 'attempts', 'max_attempts', 'created_at']);
        
        return response()->json([
            'count' => count($emails),
            'data' => $emails,
        ]);
    }
    
    /**
     * Lister les emails échoués
     * GET /api/admin/email-stats/failed
     */
    public function failedEmails(): JsonResponse
    {
        $emails = EmailJobQueue::where('status', 'failed')
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get(['id', 'email', 'subject', 'error', 'attempts', 'updated_at']);
        
        return response()->json([
            'count' => count($emails),
            'data' => $emails,
        ]);
    }
    
    /**
     * Obtenir les détails d'un job email
     * GET /api/admin/email-stats/job/{id}
     */
    public function jobDetails($id): JsonResponse
    {
        $job = EmailJobQueue::with('tracker')->find($id);
        
        if (!$job) {
            return response()->json(['error' => 'Job non trouvé'], 404);
        }
        
        return response()->json($job);
    }
    
    /**
     * Traiter manuellement les emails en attente (pour test local)
     * POST /api/admin/email-stats/process
     */
    public function processManually(): JsonResponse
    {
        $result = EmailJobQueueService::processPending(10);
        
        return response()->json([
            'message' => 'Emails traités',
            'processed' => $result['processed'],
            'failed' => $result['failed'],
        ]);
    }
    
    /**
     * Réessayer les emails échoués (pour test local)
     * POST /api/admin/email-stats/retry-failed
     */
    public function retryFailed(): JsonResponse
    {
        $count = EmailJobQueueService::retryFailed();
        
        return response()->json([
            'message' => 'Emails remis en file',
            'count' => $count,
        ]);
    }
}
