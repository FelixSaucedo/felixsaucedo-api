<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\AdminNotificationMail;
use App\Mail\UserAcknowledgmentMail;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();
        config(['portfolio.contact_recipient' => 'admin@company.com', 'mail.from.address' => 'contacto@company.com']);
    }

    protected function tearDown(): void
    {
        Validator::fakeDnsLookups(false);
        parent::tearDown();
    }

    public function test_contact_queues_two_messages_with_distinct_recipients_and_sanitized_content(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '2001:db8::1'])
            ->withHeader('User-Agent', 'Contact Test Client')
            ->postJson('/api/v1/contact', [
                ...$this->contactDetails(),
                'name' => '  <b>Ana</b>  ',
                'subject' => ' <em>Consulta técnica</em> ',
                'message' => ' <p>Necesito ayuda.</p> ',
                'status' => 'replied',
                'ip_address' => '192.0.2.99',
                'user_agent' => 'Forged client',
            ])->assertCreated()->assertExactJson(['success' => true, 'message' => 'Message processed successfully.']);

        $contact = Contact::query()->sole();
        $this->assertSame('Ana', $contact->name);
        $this->assertSame('Consulta técnica', $contact->subject);
        $this->assertSame('Necesito ayuda.', $contact->message);
        $this->assertSame('unread', $contact->status);
        $this->assertSame('2001:db8::1', $contact->ip_address);
        $this->assertSame('Contact Test Client', $contact->user_agent);
        Mail::assertQueued(AdminNotificationMail::class, fn (AdminNotificationMail $mail): bool =>
            $mail->hasTo('admin@company.com') && $mail->contact->is($contact)
            && $mail->envelope()->subject === '[Portafolio] Consulta técnica');
        Mail::assertQueued(UserAcknowledgmentMail::class, fn (UserAcknowledgmentMail $mail): bool =>
            $mail->hasTo('ana@company.com') && $mail->contact->is($contact)
            && $mail->envelope()->subject === 'Mensaje recibido — Félix Saucedo');
        Mail::assertQueuedCount(2);
        Mail::assertNothingSent();
    }

    public function test_header_line_breaks_are_removed_and_sql_input_is_stored_as_plain_text(): void
    {
        Mail::fake();
        $existingContact = Contact::factory()->create();
        $message = "'); DROP TABLE contacts; --";

        $this->postJson('/api/v1/contact', [
            ...$this->contactDetails(),
            'subject' => "Consulta\r\nBcc: spam@company.com",
            'message' => $message,
        ])->assertCreated();

        $this->assertDatabaseCount('contacts', 2);
        $this->assertDatabaseHas('contacts', ['id' => $existingContact->id]);
        $this->assertDatabaseHas('contacts', ['message' => $message, 'subject' => 'Consulta Bcc: spam@company.com']);
        Mail::assertQueuedCount(2);
    }

    public function test_optional_subject_uses_general_subject_and_admin_recipient_falls_back_to_sender(): void
    {
        Mail::fake();
        config(['portfolio.contact_recipient' => null]);

        $this->postJson('/api/v1/contact', $this->contactDetails())->assertCreated();

        $this->assertNull(Contact::query()->sole()->subject);
        Mail::assertQueued(AdminNotificationMail::class, fn (AdminNotificationMail $mail): bool =>
            $mail->hasTo('contacto@company.com') && $mail->envelope()->subject === '[Portafolio] Nuevo contacto de Ana');
    }

    #[DataProvider('invalidContactFields')]
    public function test_invalid_fields_return_json_without_contacts_or_mail(string $field, mixed $value): void
    {
        Mail::fake();

        $this->postJson('/api/v1/contact', [...$this->contactDetails(), $field => $value])
            ->assertUnprocessable()->assertHeader('Content-Type', 'application/json')->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('jobs', 0);
        Mail::assertNothingOutgoing();
    }

    public static function invalidContactFields(): array
    {
        return [
            'missing name' => ['name', null],
            'long name' => ['name', str_repeat('a', 121)],
            'array name' => ['name', ['Ana']],
            'invalid email' => ['email', 'invalid'],
            'long email' => ['email', str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 20).'.com'],
            'long subject' => ['subject', str_repeat('a', 151)],
            'array subject' => ['subject', ['Consulta']],
            'empty sanitized message' => ['message', '<p></p>'],
            'long message' => ['message', str_repeat('a', 3001)],
            'array message' => ['message', ['Consulta']],
            'unsupported locale' => ['locale', 'fr'],
            'array locale' => ['locale', ['en']],
        ];
    }

    public function test_accepts_exact_field_limits_and_an_empty_honeypot(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/contact', [
            ...$this->contactDetails(),
            'name' => str_repeat('á', 120),
            'subject' => str_repeat('á', 150),
            'message' => str_repeat('á', 3000),
            '_hp_company_url' => '',
        ])->assertCreated();

        $this->assertDatabaseCount('contacts', 1);
        Mail::assertQueuedCount(2);
    }

    public function test_contact_and_both_database_jobs_are_persisted_atomically(): void
    {
        $this->postJson('/api/v1/contact', $this->contactDetails())->assertCreated();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('jobs', 2);
        $this->assertSame(['contacts', 'contacts'], DB::table('jobs')->pluck('queue')->all());

        $this->artisan('queue:work', ['connection' => 'contact-mail', '--queue' => 'contacts', '--stop-when-empty' => true])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_queue_failure_preserves_contact_and_recovers_without_partial_or_duplicate_mail(): void
    {
        // Reject the second job to verify pending dispatch recovery without duplicating the first job.
        DB::unprepared("CREATE TRIGGER reject_second_mail BEFORE INSERT ON jobs WHEN (SELECT COUNT(*) FROM jobs) >= 1 BEGIN SELECT RAISE(FAIL, 'Queue unavailable'); END");

        $this->postJson('/api/v1/contact', $this->contactDetails())->assertAccepted()->assertExactJson([
            'success' => true,
            'message' => 'Message saved. Email notifications are pending.',
            'notification_status' => 'pending',
        ]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_submissions', 1);
        $this->assertNull(Contact::query()->sole()->mail_queued_at);
        $this->assertDatabaseCount('jobs', 0);

        DB::unprepared('DROP TRIGGER reject_second_mail');
        $this->artisan('contacts:dispatch-pending')->assertSuccessful();
        $this->assertNotNull(Contact::query()->sole()->mail_queued_at);
        $this->assertDatabaseCount('jobs', 2);
        $this->artisan('contacts:dispatch-pending')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 2);
    }

    public function test_admin_delivery_failure_does_not_block_acknowledgment_and_is_retried(): void
    {
        $this->postJson('/api/v1/contact', $this->contactDetails())->assertCreated();
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if (str_starts_with($event->message->getSubject(), '[Portafolio]')) {
                throw new RuntimeException('Simulated SMTP failure');
            }
        });

        $this->artisan('queue:work', ['connection' => 'contact-mail', '--queue' => 'contacts', '--stop-when-empty' => true])->assertSuccessful();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $queuedMessage = DB::table('jobs')->sole();
        $this->assertSame(1, $queuedMessage->attempts);
        $this->assertGreaterThan(time(), $queuedMessage->available_at);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Mensaje recibido — Félix Saucedo', $messages->first()->getOriginalMessage()->getSubject());

        Event::forget(MessageSending::class);
        $this->travel(61)->seconds();
        $this->artisan('queue:work', ['connection' => 'contact-mail', '--queue' => 'contacts', '--stop-when-empty' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(2, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_email_views_escape_html_in_all_contact_content(): void
    {
        $contact = Contact::factory()->make([
            'name' => '<script>alert(1)</script>',
            'subject' => '<img src=x onerror=alert(1)>',
            'message' => '<script>alert(2)</script> & details',
        ]);

        (new AdminNotificationMail($contact))->assertSeeInHtml('&lt;script&gt;alert(2)&lt;/script&gt; &amp; details', escape: false)
            ->assertDontSeeInHtml('<script>', escape: false)->assertDontSeeInHtml('<img', escape: false);
        (new UserAcknowledgmentMail($contact))->assertSeeInHtml('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertDontSeeInHtml('<script>', escape: false);
    }

    #[DataProvider('acknowledgmentLanguages')]
    public function test_acknowledgment_renders_the_selected_language_after_queue_delivery(?string $locale, ?string $theme, string $cardColor, string $subject, string $greeting, string $fallbackSubject, string $adminTitle): void
    {
        $this->postJson('/api/v1/contact', [...$this->contactDetails(), 'locale' => $locale, 'theme' => $theme])->assertCreated();

        $contact = Contact::query()->sole();
        $this->assertSame($locale, $contact->locale);
        $this->assertSame($theme, $contact->theme);
        $this->artisan('queue:work', ['connection' => 'contact-mail', '--queue' => 'contacts', '--stop-when-empty' => true])->assertSuccessful();

        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        $acknowledgment = $messages->last()->getOriginalMessage();
        $this->assertSame($subject, $acknowledgment->getSubject());
        $this->assertStringContainsString($greeting, $acknowledgment->getHtmlBody());
        $this->assertStringContainsString($fallbackSubject, $acknowledgment->getHtmlBody());
        $this->assertStringContainsString('bgcolor="'.$cardColor.'"', $acknowledgment->getHtmlBody());
        $this->assertStringContainsString('href="https://felixsaucedo.com"', $acknowledgment->getHtmlBody());

        $admin = $messages->first()->getOriginalMessage();
        $this->assertStringContainsString($adminTitle, $admin->getHtmlBody());
        $this->assertStringContainsString('bgcolor="'.$cardColor.'"', $admin->getHtmlBody());
        $this->assertStringContainsString('mailto:ana@company.com?subject=Re%3A%20', $admin->getHtmlBody());
    }

    public static function acknowledgmentLanguages(): array
    {
        return [
            'English dark' => ['en', 'dark', '#080d15', 'Message received — Felix Saucedo', 'Hello Ana,', 'General Inquiry', 'New message received'],
            'English light' => ['en', 'light', '#ffffff', 'Message received — Felix Saucedo', 'Hello Ana,', 'General Inquiry', 'New message received'],
            'Spanish dark' => ['es', 'dark', '#080d15', 'Mensaje recibido — Félix Saucedo', 'Hola Ana,', 'Consulta General', 'Nuevo mensaje recibido'],
            'Spanish light' => ['es', 'light', '#ffffff', 'Mensaje recibido — Félix Saucedo', 'Hola Ana,', 'Consulta General', 'Nuevo mensaje recibido'],
            'default Spanish and adaptive theme' => [null, null, '#ffffff', 'Mensaje recibido — Félix Saucedo', 'Hola Ana,', 'Consulta General', 'Nuevo mensaje recibido'],
        ];
    }

    public function test_email_without_a_site_theme_supports_client_dark_mode(): void
    {
        $contact = Contact::factory()->make(['theme' => null]);

        (new UserAcknowledgmentMail($contact))->assertSeeInHtml('@media (prefers-color-scheme: dark)', escape: false)
            ->assertSeeInHtml('[data-ogsc] .card-box', escape: false);
    }

    public function test_admin_reply_link_encodes_special_characters_in_the_subject(): void
    {
        $contact = Contact::factory()->make(['email' => 'ana@company.com', 'subject' => 'Diseño & API?']);

        (new AdminNotificationMail($contact))->assertSeeInHtml('mailto:ana@company.com?subject=Re%3A%20Dise%C3%B1o%20%26%20API%3F', escape: false);
    }

    #[DataProvider('selectedContactReasons')]
    public function test_selected_contact_reason_is_preserved_in_both_emails(string $locale, string $reason): void
    {
        Mail::fake();

        $this->postJson('/api/v1/contact', [
            ...$this->contactDetails(),
            'locale' => $locale,
            'subject' => $reason,
        ])->assertCreated();

        $contact = Contact::query()->sole();
        $this->assertSame($reason, $contact->subject);
        Mail::assertQueued(AdminNotificationMail::class, fn (AdminNotificationMail $mail): bool =>
            $mail->envelope()->subject === '[Portafolio] '.$reason);
        Mail::assertQueued(UserAcknowledgmentMail::class);
        (new AdminNotificationMail($contact))->assertSeeInHtml($reason);
        (new UserAcknowledgmentMail($contact))->assertSeeInHtml($reason);
    }

    public static function selectedContactReasons(): array
    {
        return [
            'Spanish job' => ['es', 'Oportunidad laboral (Senior / Lead Hands-on)'],
            'Spanish consulting' => ['es', 'Consultoría o desarrollo técnico'],
            'Spanish technical chat' => ['es', 'Conversación técnica / Pregunta sobre mi trabajo'],
            'English job' => ['en', 'Job Opportunity (Senior / Hands-on Lead)'],
            'English consulting' => ['en', 'Consulting or Technical Development'],
            'English technical chat' => ['en', 'Technical Chat / Question about my work'],
        ];
    }

    private function contactDetails(): array
    {
        return ['name' => 'Ana', 'email' => 'ana@company.com', 'message' => 'Me gustaría conversar.'];
    }
}
