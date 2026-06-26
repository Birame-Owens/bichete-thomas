<?php

namespace App\Jobs;

use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPaymentRetryEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Commande $commande,
        public string $newPaymentUrl
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('📧 Envoi email relance paiement', [
                'commande_id' => $this->commande->id,
                'numero' => $this->commande->numero_commande,
                'client_email' => $this->commande->client->email
            ]);

            Mail::send('emails.payment-retry', [
                'commande' => $this->commande,
                'client' => $this->commande->client,
                'paymentUrl' => $this->newPaymentUrl,
                'montant' => number_format($this->commande->montant_total, 0, ',', ' ') . ' FCFA'
            ], function ($message) {
                $message->to($this->commande->client->email, $this->commande->client->prenom . ' ' . $this->commande->client->nom)
                    ->subject('🔄 Relance de paiement - Commande ' . $this->commande->numero_commande)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            Log::info('✅ Email relance paiement envoyé', [
                'commande_id' => $this->commande->id,
                'email' => $this->commande->client->email
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur envoi email relance paiement', [
                'commande_id' => $this->commande->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
