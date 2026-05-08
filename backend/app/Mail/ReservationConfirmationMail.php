<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
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
        $reservationId = $this->receipt['reservation_id'] ?? null;
        $numeroRecu = $this->receipt['numero_recu'] ?? null;
        $label = $reservationId ? "Reservation #{$reservationId}" : 'Reservation';
        $suffix = $numeroRecu ? " - Recu {$numeroRecu}" : '';

        return new Envelope(
            subject: "Confirmation {$label}{$suffix}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-confirmation',
            with: [
                'receipt' => $this->receipt,
            ]
        );
    }
}
