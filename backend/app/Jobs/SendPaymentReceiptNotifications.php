<?php

namespace App\Jobs;

use App\Models\Paiement;
use App\Services\PaymentReceiptNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Envoi asynchrone des notifications de reçu de paiement (I6).
 *
 * Avant : PaymentReceiptNotificationService::send() etait appele en plein
 * milieu du webhook Stripe / PayTech. Chaque appel = jusqu a 3 HTTP calls
 * externes (Twilio + WhatsApp Cloud + Mail SMTP) -> 1 a 5 secondes
 * bloquantes par paiement. Sous Tabaski, les workers PHP-FPM se faisaient
 * saturer immediatement, et un timeout du PSP = retry agressif = explosion.
 *
 * Maintenant : la requete HTTP repond instantanement (~50 ms) en pushant
 * le travail dans la queue Redis. Un worker dedie (php artisan queue:work)
 * consomme la queue et envoie les notifs en arriere-plan, avec retry
 * automatique en cas d echec transitoire.
 *
 * Le job prend uniquement l ID du paiement (pas le modele Eloquent) pour
 * eviter les problemes de serialisation et garantir qu on lit la version
 * la plus a jour en base au moment de l envoi (le webhook a pu changer
 * statut/montant entre le dispatch et l execution).
 */
class SendPaymentReceiptNotifications implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Nombre de tentatives en cas d echec (HTTP timeout, erreur 5xx, etc.). */
    public int $tries = 3;

    /** Delais entre tentatives en secondes (10s, 30s, 60s). Backoff progressif. */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function __construct(public readonly int $paiementId)
    {
    }

    public function handle(PaymentReceiptNotificationService $service): void
    {
        $paiement = Paiement::query()->find($this->paiementId);

        if (! $paiement) {
            // Le paiement a pu etre supprime entre le dispatch et l execution.
            // Inutile de retry, on log et on sort.
            Log::warning('SendPaymentReceiptNotifications : paiement introuvable', [
                'paiement_id' => $this->paiementId,
            ]);

            return;
        }

        $service->send($paiement);
    }
}
