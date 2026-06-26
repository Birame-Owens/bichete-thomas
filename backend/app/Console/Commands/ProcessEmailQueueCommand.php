<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailJobQueueService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessEmailQueueCommand extends Command
{
    protected $signature = 'email:process-queue {--limit=10 : Nombre d\'emails à traiter}';
    protected $description = 'Traiter la file d\'attente des emails avec throttling';

    public function handle(): int
    {
        $limit = $this->option('limit');
        
        $this->info("📧 Traitement de la file d'attente des emails...");
        
        // Récupérer les prochains emails
        $emails = DB::table('email_job_queues')
            ->where('status', 'pending')
            ->where('attempts', '<', 3)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
        
        if ($emails->isEmpty()) {
            $this->info("✅ Aucun email à traiter");
            return 0;
        }
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($emails as $emailRecord) {
            try {
                // Marquer comme en traitement
                DB::table('email_job_queues')
                    ->where('id', $emailRecord->id)
                    ->update(['status' => 'processing']);
                
                // Décoder les données
                $data = json_decode($emailRecord->data, true) ?? [];
                
                // Envoyer l'email
                Mail::send($emailRecord->template, $data, function ($message) use ($emailRecord, $data) {
                    $message->to($emailRecord->email)
                        ->subject($emailRecord->subject);
                });
                
                // Marquer comme envoyé
                DB::table('email_job_queues')
                    ->where('id', $emailRecord->id)
                    ->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                
                $this->info("✅ Email envoyé à {$emailRecord->email}");
                $successCount++;
                
                // Throttling: pause de 100ms entre chaque email
                usleep(100000);
                
            } catch (\Exception $e) {
                $attempts = $emailRecord->attempts + 1;
                
                DB::table('email_job_queues')
                    ->where('id', $emailRecord->id)
                    ->update([
                        'status' => $attempts >= 3 ? 'failed' : 'pending',
                        'attempts' => $attempts,
                        'last_error' => $e->getMessage(),
                    ]);
                
                $this->error("❌ Erreur pour {$emailRecord->email}: {$e->getMessage()}");
                $failCount++;
                
                Log::error('Erreur envoi email', [
                    'email_id' => $emailRecord->id,
                    'email' => $emailRecord->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        $this->info("\n📊 Résumé:");
        $this->info("  ✅ Succès: $successCount");
        $this->info("  ❌ Échoués: $failCount");
        
        return 0;
    }
}
