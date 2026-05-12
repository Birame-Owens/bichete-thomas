<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Services\ClientMagicLinkService;
use App\Services\WhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envoie le magic link WhatsApp au client apres validation du paiement.
 *
 * Dispatche de facon asynchrone (meme pattern que SendPaymentReceiptNotifications)
 * pour ne pas bloquer la reponse webhook. Le client recoit le lien dans les
 * secondes qui suivent la confirmation de paiement.
 *
 * Si WhatsApp n est pas configure (dev, test), le job log un warning et sort
 * proprement sans faire planter la queue.
 */
class SendMagicLinkNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly int $paiementId) {}

    public function handle(ClientMagicLinkService $magicLink, WhatsappService $whatsapp): void
    {
        $paiement = Paiement::query()->with(['client', 'reservation.client'])->find($this->paiementId);

        if (! $paiement) {
            Log::warning('SendMagicLinkNotification : paiement introuvable', ['paiement_id' => $this->paiementId]);

            return;
        }

        $client = $paiement->client ?? $paiement->reservation?->client;

        if (! $client || ! $client->telephone) {
            return;
        }

        $rawToken = $magicLink->generateMagicLink($client);
        $url = $magicLink->buildUrl($rawToken);

        $message = implode("\n", [
            "Bonjour {$client->prenom},",
            'Votre reservation chez Bichette Thomas est confirmee.',
            '',
            'Retrouvez et gerez vos reservations en 1 clic :',
            $url,
            '',
            'Ce lien est valable 24h.',
        ]);

        $sent = $whatsapp->send($client->telephone, $message, 'magic-link-' . $client->id);

        if (! $sent) {
            Log::info('SendMagicLinkNotification : WhatsApp non configure ou inaccessible', [
                'client_id' => $client->id,
            ]);
        }
    }
}
