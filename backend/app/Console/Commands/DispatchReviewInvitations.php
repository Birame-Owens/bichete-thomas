<?php

namespace App\Console\Commands;

use App\Jobs\SendReviewInvitation;
use App\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Trouve les reservations terminees depuis au moins 24h sans invitation
 * deja envoyee et dispatche SendReviewInvitation pour chacune.
 *
 * Tourne en cron quotidien a 10h (console.php). Idempotent : review_invited_at
 * garantit qu une reservation n est invitee qu une seule fois meme si le
 * cron tourne plusieurs fois dans la journee.
 *
 * Usage :
 *   php artisan reviews:dispatch-invitations
 *   php artisan reviews:dispatch-invitations --dry-run
 */
class DispatchReviewInvitations extends Command
{
    protected $signature = 'reviews:dispatch-invitations {--dry-run : Affiche les reservations eligibles sans envoyer}';

    protected $description = 'Envoie les invitations avis WhatsApp pour les reservations terminees depuis 24h.';

    public function handle(): int
    {
        $reservations = Reservation::query()
            ->with('client')
            ->where('statut', 'terminee')
            ->whereNull('review_invited_at')
            ->whereDate('date_reservation', '<=', now()->subDay()->toDateString())
            ->whereHas('client', fn ($q) => $q->whereNotNull('telephone'))
            ->get();

        $this->info("Reservations eligibles : {$reservations->count()}");

        if ($this->option('dry-run')) {
            foreach ($reservations as $reservation) {
                $this->line("  [dry-run] #{$reservation->id} — {$reservation->client->prenom} {$reservation->client->nom} ({$reservation->date_reservation})");
            }

            return self::SUCCESS;
        }

        foreach ($reservations as $reservation) {
            SendReviewInvitation::dispatch($reservation->id);
        }

        $this->info("Jobs dispatches : {$reservations->count()}");

        return self::SUCCESS;
    }
}
