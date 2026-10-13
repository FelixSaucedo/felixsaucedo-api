<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->timestamp('mail_queued_at')->nullable()->index();
        });
        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->unique()->constrained('contacts')->cascadeOnDelete();
        });

        DB::table('contacts')->orderBy('id')->chunkById(100, function (Collection $contacts): void {
            foreach ($contacts as $contact) {
                DB::table('contact_submissions')->insert([
                    'contact_id' => $contact->id,
                    'uuid' => (string) Str::uuid(),
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'subject' => $contact->subject,
                    'message' => $contact->message,
                    'ip_hash' => hash('sha256', $contact->ip_address ?? ''),
                    'user_agent' => $contact->user_agent === null ? null : mb_substr($contact->user_agent, 0, 255),
                    'status' => $contact->status === 'unread' ? 'new' : 'reviewed',
                    'created_at' => $contact->created_at,
                    'updated_at' => $contact->updated_at,
                ]);
            }
        });

        // Mark historical contacts as queued so recovery does not resend their mail.
        DB::table('contacts')->update(['mail_queued_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('contact_submissions', function (Blueprint $table): void {
            $table->dropForeign(['contact_id']);
            $table->dropUnique(['contact_id']);
            $table->dropColumn('contact_id');
        });
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex(['mail_queued_at']);
            $table->dropColumn('mail_queued_at');
        });
    }
};
