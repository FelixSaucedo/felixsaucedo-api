<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAcknowledgmentMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public Contact $contact)
    {
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Gracias por ponerte en contacto — Félix Saucedo',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user_acknowledgment');
    }
}
