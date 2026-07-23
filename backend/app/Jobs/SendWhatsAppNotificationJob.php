<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MessagesWhatsapp;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;
    public $backoff = [10, 30, 60]; // Retry après 10s, 30s, 60s

    protected $phoneNumber;
    protected $message;
    protected $type;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phoneNumber, string $message, string $type = 'notification')
    {
        $this->phoneNumber = $this->formatPhoneNumber($phoneNumber);
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Configuration Twilio
            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioWhatsAppNumber = config('services.twilio.whatsapp_number');

            if (!$twilioSid || !$twilioToken || !$twilioWhatsAppNumber) {
                Log::warning('Twilio non configuré, impossible d\'envoyer WhatsApp', [
                    'to' => $this->phoneNumber,
                    'type' => $this->type,
                ]);
                return;
            }

            $twilio = new TwilioClient($twilioSid, $twilioToken);

            // Envoyer le message WhatsApp via Twilio
            $messageResponse = $twilio->messages->create(
                "whatsapp:{$this->phoneNumber}",
                [
                    'from' => "whatsapp:{$twilioWhatsAppNumber}",
                    'body' => $this->message,
                ]
            );

            // Enregistrer dans la base de données
            MessagesWhatsapp::create([
                'destinataire' => $this->phoneNumber,
                'message' => $this->message,
                'type' => $this->type,
                'statut' => 'envoye',
                'reference_externe' => $messageResponse->sid,
                'date_envoi' => now(),
            ]);

            Log::info('WhatsApp envoyé avec succès', [
                'to' => $this->phoneNumber,
                'sid' => $messageResponse->sid,
                'type' => $this->type,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur envoi WhatsApp', [
                'to' => $this->phoneNumber,
                'error' => $e->getMessage(),
                'type' => $this->type,
            ]);

            // Enregistrer l'échec
            MessagesWhatsapp::create([
                'destinataire' => $this->phoneNumber,
                'message' => $this->message,
                'type' => $this->type,
                'statut' => 'echoue',
                'erreur' => $e->getMessage(),
                'date_envoi' => now(),
            ]);

            // Relancer le job si pas au dernier essai
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1]);
            }
        }
    }

    /**
     * Formater le numéro de téléphone au format international
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Retirer tous les caractères non numériques sauf le +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Si commence par 00, remplacer par +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // Si ne commence pas par +, ajouter +221 (Sénégal)
        if (!str_starts_with($phone, '+')) {
            // Si commence par 7 (numéro sénégalais local)
            if (str_starts_with($phone, '7')) {
                $phone = '+221' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Échec définitif envoi WhatsApp', [
            'to' => $this->phoneNumber,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mettre à jour le statut dans la base
        MessagesWhatsapp::where('destinataire', $this->phoneNumber)
            ->where('message', $this->message)
            ->where('statut', '!=', 'envoye')
            ->latest()
            ->first()
            ?->update([
                'statut' => 'echoue_definitif',
                'erreur' => $exception->getMessage(),
            ]);
    }
}
