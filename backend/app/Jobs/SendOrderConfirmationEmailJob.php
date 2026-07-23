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

class SendOrderConfirmationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $commande;
    protected $temporaryPassword;
    protected $isNewAccount;

    /**
     * Create a new job instance.
     */
    public function __construct(Commande $commande, $temporaryPassword = null, $isNewAccount = false)
    {
        $this->commande = $commande;
        $this->temporaryPassword = $temporaryPassword;
        $this->isNewAccount = $isNewAccount;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $commande = $this->commande->load(['client', 'articles_commandes.produit', 'paiements']);

            Mail::send('emails.order-confirmation', [
                'commande' => $commande,
                'client' => $commande->client,
                'temporaryPassword' => $this->temporaryPassword,
                'isNewAccount' => $this->isNewAccount,
            ], function ($message) use ($commande) {
                $subject = $this->isNewAccount 
                    ? "✅ Bienvenue ! Commande N°{$commande->numero_commande} confirmée - NDEYA SHOP"
                    : "✅ Commande confirmée N°{$commande->numero_commande} - NDEYA SHOP";
                    
                $message->to($commande->client->email, $commande->client->prenom . ' ' . $commande->client->nom)
                    ->subject($subject);
            });

            Log::info('Email confirmation commande envoyé', [
                'commande_id' => $commande->id,
                'email' => $commande->client->email,
                'is_new_account' => $this->isNewAccount,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur envoi email confirmation commande', [
                'commande_id' => $this->commande->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
