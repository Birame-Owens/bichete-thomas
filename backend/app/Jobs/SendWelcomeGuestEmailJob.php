<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SendWelcomeGuestEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 30;

    protected $client;
    protected $commande;

    /**
     * Create a new job instance.
     */
    public function __construct(Client $client, Commande $commande)
    {
        $this->client = $client;
        $this->commande = $commande;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Générer token pour création de compte
            $token = Str::random(64);
            
            // Stocker dans cache pour 7 jours
            cache()->put(
                "guest_account_token:{$token}",
                [
                    'client_id' => $this->client->id,
                    'email' => $this->client->email,
                ],
                now()->addDays(7)
            );

            $accountCreationUrl = config('app.url') . "/creer-compte?token={$token}";

            Mail::send('emails.guest-welcome', [
                'client' => $this->client,
                'commande' => $this->commande,
                'accountCreationUrl' => $accountCreationUrl,
            ], function ($message) {
                $message->to($this->client->email, $this->client->prenom . ' ' . $this->client->nom)
                    ->subject('🎉 Bienvenue chez NDEYA SHOP - Créez votre compte');
            });

            Log::info('Email bienvenue invité envoyé', [
                'client_id' => $this->client->id,
                'email' => $this->client->email,
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur envoi email bienvenue invité', [
                'client_id' => $this->client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
