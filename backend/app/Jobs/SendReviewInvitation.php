<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Envoie l invitation a laisser un avis verifie pour une reservation terminee.
 *
 * Dispatche par la commande reviews:dispatch-invitations (cron quotidien 10h).
 * Genere le token single-use, l enregistre en DB, puis envoie le lien
 * via WhatsApp. review_invited_at est pose AVANT l envoi pour que meme un
 * echec WhatsApp ne provoque pas un double-envoi au prochain passage du cron.
 */
class SendReviewInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly int $reservationId) {}

    public function handle(WhatsappService $whatsapp): void
    {
        $reservation = Reservation::query()
            ->with(['client', 'details'])
            ->find($this->reservationId);

        // Idempotence : si deja invite (race condition entre workers), on s arrete.
        if (! $reservation || $reservation->review_invited_at !== null) {
            return;
        }

        $client = $reservation->client;

        if (! $client?->telephone) {
            return;
        }

        $raw = Str::random(64);
        $coiffureNom = $reservation->details->first()?->coiffure_nom ?? 'votre prestation';
        $url = rtrim((string) config('app.frontend_url', config('app.url')), '/') . '/avis/' . $raw;

        // Pose review_invited_at d abord : meme si l envoi WhatsApp echoue,
        // le cron ne re-dispatche pas et le token reste utilisable 7 jours.
        $reservation->forceFill([
            'review_token' => hash('sha256', $raw),
            'review_token_expires_at' => now()->addDays(7),
            'review_invited_at' => now(),
        ])->save();

        $message = "Bonjour {$client->prenom} 👋\n\n"
            . "Comment s'est passée votre prestation {$coiffureNom} ?\n\n"
            . "Laissez un avis en 30 secondes ici :\n{$url}\n\n"
            . "(Lien valable 7 jours)";

        $whatsapp->send($client->telephone, $message, 'review_invitation');
    }
}
