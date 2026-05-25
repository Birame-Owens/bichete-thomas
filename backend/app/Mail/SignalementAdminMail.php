<?php

namespace App\Mail;

use App\Models\Signalement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignalementAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Signalement $signalement)
    {
    }

    public function envelope(): Envelope
    {
        $urgence = $this->signalement->urgence === 'urgente' ? '[URGENT] ' : '';

        return new Envelope(
            subject: "{$urgence}Signalement : {$this->signalement->titre}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.signalement-admin',
            with: ['signalement' => $this->signalement],
        );
    }
}
