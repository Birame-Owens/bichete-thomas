<?php

namespace App\Jobs;

use App\Models\Commande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAdminOrderNotificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected $commande;

    /**
     * Create a new job instance.
     */
    public function __construct(Commande $commande)
    {
        $this->commande = $commande;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $adminEmail = config('services.admin_notifications.email');

        if (!$adminEmail) {
            Log::warning('Admin notification email not configured', [
                'commande_id' => $this->commande->id,
            ]);
            return;
        }

        try {
            $commande = $this->commande->load(['client', 'articles_commandes.produit', 'paiements']);

            Mail::send('emails.admin-order-notification', [
                'commande' => $commande,
                'client' => $commande->client,
            ], function ($message) use ($adminEmail, $commande) {
                $subject = "Nouvelle commande confirmee No {$commande->numero_commande}";
                $message->to($adminEmail)->subject($subject);
            });

            Log::info('Admin order notification email sent', [
                'commande_id' => $commande->id,
                'email' => $adminEmail,
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending admin order notification email', [
                'commande_id' => $this->commande->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
