<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNotificationMail extends Mailable implements ShouldQueue
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
            subject: '[Nuevo Contacto Portafolio]: '.($this->contact->subject ?: 'Consulta General'),
            replyTo: [new Address($this->contact->email, $this->contact->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin_notification');
    }
}
