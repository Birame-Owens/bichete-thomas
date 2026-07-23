<?php

namespace App\Services;

use App\Models\EmailJobQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\MonitoringService;

/**
 * Service pour gérer la file d'attente des emails avec throttling & déduplication
 */
class EmailJobQueueService
{
    /**
     * Ajouter un email à la file avec déduplication
     */
    public static function queue(string $email, string $subject, array $data = [], string $template = 'emails.generic')
    {
        // Créer une signature unique pour déduplication
        $signature = hash('sha256', $email . $subject . json_encode($data));
        
        // Vérifier si email identique est déjà en cours
        $exists = EmailJobQueue::where('signature', $signature)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
        
        if ($exists) {
            Log::info('Email déduplicaté', ['email' => $email, 'subject' => $subject]);
            return false;
        }
        
        // Créer l'entrée en file d'attente
        $job = EmailJobQueue::create([
            'email' => $email,
            'subject' => $subject,
            'template' => $template,
            'data' => $data,
            'signature' => $signature,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);
        
        Log::info('Email ajouté à la file', ['job_id' => $job->id, 'email' => $email]);
        
        return $job;
    }
    
    /**
     * Traiter les emails de la file avec throttling
     * À appeler via artisan command toutes les minutes
     */
    public static function processPending(int $batchSize = 5)
    {
        $jobs = EmailJobQueue::where('status', 'pending')
            ->whereRaw('attempts < max_attempts')
            ->orderBy('created_at', 'asc')
            ->limit($batchSize)
            ->get();
        
        $processed = 0;
        $failed = 0;
        
        foreach ($jobs as $job) {
            try {
                $job->update(['status' => 'processing']);
                
                // Envoyer l'email
                Mail::send($job->template, $job->data, function ($message) use ($job) {
                    $message->to($job->email)
                        ->subject($job->subject);
                });
                
                // Marquer comme envoyé
                $job->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'attempts' => $job->attempts + 1,
                ]);
                
                MonitoringService::logAction('email_sent', "Email envoyé à {$job->email}", null);
                $processed++;
                
                // Throttling: 0.5 seconde entre chaque email
                usleep(500000);
                
            } catch (\Exception $e) {
                $job->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'attempts' => $job->attempts + 1,
                ]);
                
                Log::error('Erreur envoi email', [
                    'job_id' => $job->id,
                    'email' => $job->email,
                    'error' => $e->getMessage(),
                ]);
                
                $failed++;
            }
        }
        
        Log::info('📧 Batch emails traité', [
            'processed' => $processed,
            'failed' => $failed,
            'batch_size' => $batchSize,
        ]);
        
        return ['processed' => $processed, 'failed' => $failed];
    }
    
    /**
     * Réessayer les emails échoués
     */
    public static function retryFailed()
    {
        $jobs = EmailJobQueue::where('status', 'failed')
            ->where('attempts', '<', 'max_attempts')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->get();
        
        $jobs->each(fn($job) => $job->update(['status' => 'pending']));
        
        Log::info('Emails échoués remis en file', ['count' => $jobs->count()]);
        
        return $jobs->count();
    }
    
    /**
     * Obtenir les stats de la file
     */
    public static function getStats()
    {
        return [
            'pending' => EmailJobQueue::where('status', 'pending')->count(),
            'processing' => EmailJobQueue::where('status', 'processing')->count(),
            'sent' => EmailJobQueue::where('status', 'sent')->count(),
            'failed' => EmailJobQueue::where('status', 'failed')->count(),
            'total_24h' => EmailJobQueue::where('created_at', '>', now()->subHours(24))->count(),
            'success_rate' => self::calculateSuccessRate(),
        ];
    }
    
    /**
     * Calculer le taux de succès
     */
    private static function calculateSuccessRate()
    {
        $total = EmailJobQueue::where('created_at', '>', now()->subDays(7))->count();
        $sent = EmailJobQueue::where('status', 'sent')
            ->where('created_at', '>', now()->subDays(7))
            ->count();
        
        return $total > 0 ? round(($sent / $total) * 100, 2) : 0;
    }
}
