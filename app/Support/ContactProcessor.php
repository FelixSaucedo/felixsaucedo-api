<?php

declare(strict_types=1);

namespace App\Support;

use App\Mail\AdminNotificationMail;
use App\Mail\UserAcknowledgmentMail;
use App\Models\Contact;
use App\Models\ContactSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ContactProcessor
{
    /** @param array{name: string, email: string, message: string, subject?: ?string, locale?: ?string, theme?: ?string} $details */
    public function process(array $details, ?string $ipAddress, ?string $userAgent): Contact
    {
        $contact = DB::transaction(function () use ($details, $ipAddress, $userAgent): Contact {
            $contact = Contact::query()->create([
                ...$details,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);
            ContactSubmission::query()->create([
                'contact_id' => $contact->id,
                'uuid' => (string) Str::uuid(),
                'name' => $contact->name,
                'email' => $contact->email,
                'subject' => $contact->subject,
                'message' => $contact->message,
                'ip_hash' => hash('sha256', $ipAddress ?? ''),
                'user_agent' => $userAgent,
            ]);

            return $contact;
        });

        $this->dispatchMail($contact);

        return $contact->refresh();
    }

    public function dispatchMail(Contact $contact): bool
    {
        try {
            DB::transaction(function () use ($contact): void {
                $pending = Contact::query()->lockForUpdate()->find($contact->id);
                if ($pending === null || $pending->mail_queued_at !== null) {
                    return;
                }

                // The database jobs and dispatch marker commit together; partial dispatch is rolled back.
                Mail::to(config('portfolio.contact_recipient') ?: config('mail.from.address'))
                    ->queue((new AdminNotificationMail($pending))->onConnection('contact-mail')->onQueue('contacts')->beforeCommit());
                Mail::to($pending->email)
                    ->queue((new UserAcknowledgmentMail($pending))->onConnection('contact-mail')->onQueue('contacts')->beforeCommit());
                $pending->mail_queued_at = now();
                $pending->save();
            });

            return true;
        } catch (Throwable $exception) {
            Log::error('Contact mail dispatch deferred.', [
                'contact_id' => $contact->id,
                'exception_type' => $exception::class,
            ]);

            return false;
        }
    }
}
