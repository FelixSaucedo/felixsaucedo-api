<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Contact;
use App\Support\ContactProcessor;
use Illuminate\Console\Command;

class DispatchPendingContactMail extends Command
{
    protected $signature = 'contacts:dispatch-pending';

    protected $description = 'Queue email notifications for saved contacts awaiting dispatch';

    public function handle(ContactProcessor $processor): int
    {
        $failed = false;
        foreach (Contact::query()->whereNull('mail_queued_at')->orderBy('id')->limit(100)->get() as $contact) {
            if (! $processor->dispatchMail($contact)) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
