<?php

namespace App\Jobs;

use App\Mail\GroupMessageMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendGroupMessageJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public $timeout = 300; // 5 minutes

    protected array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $clients = $this->data['clients'];
        $channel = $this->data['channel'];
        $subject = $this->data['subject'];
        $message = $this->data['message'];
        $adminId = $this->data['admin_id'];

        $successCount = 0;
        $failCount = 0;

        foreach ($clients as $client) {
            try {
                // Envoi par email
                if (in_array($channel, ['email', 'both'])) {
                    $this->sendEmail($client, $subject, $message);
                    $successCount++;
                }

                // Envoi par WhatsApp (si configuré)
                if (in_array($channel, ['whatsapp', 'both'])) {
                    $this->sendWhatsApp($client, $message);
                    // Ne pas incrémenter si déjà incrémenté pour email
                    if ($channel === 'whatsapp') {
                        $successCount++;
                    }
                }

                // Petite pause pour éviter de surcharger
                usleep(100000); // 0.1 seconde

            } catch (\Exception $e) {
                $failCount++;
                Log::error('Erreur envoi message client', [
                    'client_id' => $client['id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('📊 Message groupé envoyé', [
            'total' => count($clients),
            'success' => $successCount,
            'failed' => $failCount,
            'channel' => $channel,
            'admin_id' => $adminId
        ]);
    }

    protected function sendEmail(array $client, string $subject, string $message): void
    {
        $clientName = trim(($client['prenom'] ?? '') . ' ' . ($client['nom'] ?? ''));
        if (empty($clientName)) {
            $clientName = 'Cher(e) Client(e)';
        }
        
        try {
            Mail::to($client['email'])->send(
                new GroupMessageMail($clientName, $message, $subject)
            );

            Log::info('✅ Email groupé envoyé', [
                'client_id' => $client['id'],
                'email' => $client['email']
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur email groupé', [
                'client_id' => $client['id'],
                'email' => $client['email'],
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function sendWhatsApp(array $client, string $message): void
    {
        // TODO: Implémenter l'envoi WhatsApp via Twilio
        // Pour l'instant, juste logger
        Log::info('📱 WhatsApp groupé (simulation)', [
            'client_id' => $client['id'],
            'telephone' => $client['telephone'] ?? 'N/A'
        ]);
    }
}
