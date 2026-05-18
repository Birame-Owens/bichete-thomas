<?php

namespace App\Services;

use App\Mail\AdminReservationNotificationMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\Paiement;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PaymentReceiptNotificationService
{
    public function __construct(private readonly WhatsappService $whatsapp) {}

    public function send(Paiement $paiement): array
    {
        $paiement->loadMissing(['client', 'reservation.client', 'reservation.details']);

        if ($paiement->statut !== 'valide' || $paiement->recu_envoye) {
            return ['whatsapp' => false, 'email' => false, 'skipped' => true];
        }

        $receipt = $this->receiptData($paiement);
        $message = $this->receiptMessage($receipt);
        $sent = [
            'whatsapp' => false,
            'email' => false,
            'skipped' => false,
        ];

        if ((bool) config('services.receipt_notifications.whatsapp')) {
            $sent['whatsapp'] = $this->sendWhatsapp($receipt, $message);
        }

        if ((bool) config('services.receipt_notifications.email')) {
            $sent['email'] = $this->sendEmail($receipt);
            $this->sendAdminNotification($receipt);
        }

        if ($sent['whatsapp'] || $sent['email']) {
            $paiement->forceFill([
                'recu_envoye' => true,
                'recu_envoye_at' => now(),
            ])->save();
        }

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    private function receiptData(Paiement $paiement): array
    {
        $reservation = $paiement->reservation;
        $client = $paiement->client ?? $reservation?->client;
        $services = $reservation
            ? $reservation->details->map(fn ($detail): string => trim(
                implode(' - ', array_filter([$detail->coiffure_nom, $detail->variante_nom]))
            ))->filter()->values()->all()
            : [];
        $reservationPaid = $reservation ? $this->reservationPaidAmount($reservation->id) : (float) $paiement->montant;
        $reservationTotal = $reservation ? (float) $reservation->montant_total : (float) $paiement->montant;

        return [
            'numero_recu' => $paiement->numero_recu,
            'client_nom' => $client ? trim("{$client->prenom} {$client->nom}") : 'Cliente',
            'telephone' => $client?->telephone,
            'email' => $client?->email,
            'reservation_id' => $reservation?->id,
            'date_reservation' => $reservation?->date_reservation?->format('d/m/Y'),
            'heure_debut' => $reservation ? substr((string) $reservation->heure_debut, 0, 5) : null,
            'services' => $services,
            'montant_paye' => (float) $paiement->montant,
            'montant_total' => $reservationTotal,
            'reste_a_payer' => max($reservationTotal - $reservationPaid, 0),
            'devise' => $paiement->devise,
            'mode_paiement' => $paiement->mode_paiement,
            'salon_whatsapp' => $this->settingValue('telephone_whatsapp', null),
        ];
    }

    /**
     * @param array<string, mixed> $receipt
     */
    private function receiptMessage(array $receipt): string
    {
        $service = $receipt['services'][0] ?? 'Votre coiffure';
        $date = $receipt['date_reservation'] && $receipt['heure_debut']
            ? "{$receipt['date_reservation']} a {$receipt['heure_debut']}"
            : 'date a confirmer';

        return implode("\n", array_filter([
            "Bonjour {$receipt['client_nom']},",
            'Votre reservation chez Bichette Thomas est confirmee.',
            "Recu: {$receipt['numero_recu']}",
            $receipt['reservation_id'] ? "Reservation #{$receipt['reservation_id']}" : null,
            "Prestation: {$service}",
            "Date: {$date}",
            'Paiement recu: ' . $this->money($receipt['montant_paye'], $receipt['devise']) . " ({$receipt['mode_paiement']})",
            'Total: ' . $this->money($receipt['montant_total'], $receipt['devise']),
            'Reste a payer: ' . $this->money($receipt['reste_a_payer'], $receipt['devise']),
            $receipt['salon_whatsapp'] ? "Contact salon: {$receipt['salon_whatsapp']}" : null,
            'Merci et a tres bientot.',
        ]));
    }

    /**
     * @param array<string, mixed> $receipt
     */
    private function sendWhatsapp(array $receipt, string $message): bool
    {
        $to = $receipt['telephone'] ?? null;

        if (! $to) {
            return false;
        }

        $template = config('services.whatsapp.receipt_template_name');
        $context = 'recu-' . $receipt['numero_recu'];

        if ($template) {
            return $this->whatsapp->sendTemplate(
                $to,
                $template,
                [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $receipt['client_nom']],
                        ['type' => 'text', 'text' => (string) $receipt['numero_recu']],
                        ['type' => 'text', 'text' => (string) ($receipt['services'][0] ?? 'Reservation')],
                        ['type' => 'text', 'text' => trim(($receipt['date_reservation'] ?? '') . ' ' . ($receipt['heure_debut'] ?? ''))],
                        ['type' => 'text', 'text' => $this->money($receipt['montant_paye'], $receipt['devise'])],
                        ['type' => 'text', 'text' => $this->money($receipt['reste_a_payer'], $receipt['devise'])],
                    ],
                ]],
                $context,
            );
        }

        return $this->whatsapp->send($to, $message, $context);
    }

    /**
     * Notifie l'admin/gérante par mail à chaque paiement confirmé.
     * Silencieux si MAIL_ADMIN_NOTIFICATION n'est pas configuré ou si le mailer est en mode test.
     *
     * @param array<string, mixed> $receipt
     */
    private function sendAdminNotification(array $receipt): void
    {
        $adminEmail = config('services.receipt_notifications.admin_email');

        if (! $adminEmail || ! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (in_array(config('mail.default'), ['array', 'log'], true)) {
            return;
        }

        try {
            Mail::to($adminEmail)->send(new AdminReservationNotificationMail($receipt));
        } catch (\Throwable $exception) {
            Log::warning('Admin reservation notification email failed', [
                'paiement' => $receipt['numero_recu'],
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $receipt
     */
    private function sendEmail(array $receipt): bool
    {
        $email = $receipt['email'] ?? null;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (in_array(config('mail.default'), ['array', 'log'], true)) {
            Log::warning('Email receipt not delivered because mailer is not a delivery transport', [
                'paiement' => $receipt['numero_recu'],
                'mailer' => config('mail.default'),
                'email' => $email,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new ReservationConfirmationMail($receipt));

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Email receipt message exception', [
                'paiement' => $receipt['numero_recu'],
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function reservationPaidAmount(int $reservationId): float
    {
        $incoming = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->where('statut', 'valide')
            ->whereIn('type', ['acompte', 'solde', 'complet', 'ajustement'])
            ->sum('montant');

        $refunds = Paiement::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('statut', ['valide', 'rembourse'])
            ->where('type', 'remboursement')
            ->sum('montant');

        return max((float) $incoming - (float) $refunds, 0);
    }

    private function money(float|int|string $amount, string $devise): string
    {
        return number_format((float) $amount, 0, ',', ' ') . " {$devise}";
    }

    private function settingValue(string $key, mixed $default): mixed
    {
        // Delegue a SystemSettings (cache I7).
        return SystemSettings::get($key, $default);
    }
}
