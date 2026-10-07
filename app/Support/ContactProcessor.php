<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\AdminNotificationMail;
use App\Mail\UserAcknowledgmentMail;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ContactProcessor
{
    /** @param array{name: string, email: string, message: string, subject?: ?string} $details */
    public function process(array $details, ?string $ipAddress, ?string $userAgent): Contact
    {
        return DB::transaction(function () use ($details, $ipAddress, $userAgent): Contact {
            $contact = Contact::query()->create([
                ...$details,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            // Both database jobs share the contact transaction; workers can only see committed records.
            Mail::to(config('portfolio.contact_recipient') ?: config('mail.from.address'))
                ->queue((new AdminNotificationMail($contact))->onConnection('contact-mail')->onQueue('contacts')->beforeCommit());
            Mail::to($contact->email)
                ->queue((new UserAcknowledgmentMail($contact))->onConnection('contact-mail')->onQueue('contacts')->beforeCommit());

            return $contact;
        });
    }
}
