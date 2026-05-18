<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminReservationNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $receipt
     */
    public function __construct(public array $receipt)
    {
    }

    public function envelope(): Envelope
    {
        $clientNom = $this->receipt['client_nom'] ?? 'Cliente';
        $reservationId = $this->receipt['reservation_id'] ?? null;
        $label = $reservationId ? " #${reservationId}" : '';

        return new Envelope(
            subject: "Nouvelle reservation{$label} — {$clientNom}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-reservation-notification',
            with: [
                'receipt' => $this->receipt,
            ]
        );
    }
}
