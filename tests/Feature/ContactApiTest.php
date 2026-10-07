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
            && $mail->envelope()->subject === '[Nuevo Contacto Portafolio]: Consulta técnica');
        Mail::assertQueued(UserAcknowledgmentMail::class, fn (UserAcknowledgmentMail $mail): bool =>
            $mail->hasTo('ana@company.com') && $mail->contact->is($contact)
            && $mail->envelope()->subject === 'Gracias por ponerte en contacto — Félix Saucedo');
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
            $mail->hasTo('contacto@company.com') && $mail->envelope()->subject === '[Nuevo Contacto Portafolio]: Consulta General');
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

    public function test_queue_failure_rolls_back_contact_and_the_first_queued_message(): void
    {
        // Fail the second enqueue after the first succeeds to verify the complete transaction rollback.
        DB::unprepared("CREATE TRIGGER reject_second_mail BEFORE INSERT ON jobs WHEN (SELECT COUNT(*) FROM jobs) >= 1 BEGIN SELECT RAISE(FAIL, 'Queue unavailable'); END");

        $this->postJson('/api/v1/contact', $this->contactDetails())->assertStatus(500);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_admin_delivery_failure_does_not_block_acknowledgment_and_is_retried(): void
    {
        $this->postJson('/api/v1/contact', $this->contactDetails())->assertCreated();
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if (str_starts_with($event->message->getSubject(), '[Nuevo Contacto Portafolio]')) {
                throw new RuntimeException('Simulated SMTP failure');
            }
        });

        $this->artisan('queue:work', ['connection' => 'contact-mail', '--queue' => 'contacts', '--stop-when-empty' => true])->assertSuccessful();

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('failed_jobs', 0);
        $remainingMail = DB::table('jobs')->sole();
        $this->assertSame(1, $remainingMail->attempts);
        $this->assertGreaterThan(time(), $remainingMail->available_at);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('Gracias por ponerte en contacto — Félix Saucedo', $messages->first()->getOriginalMessage()->getSubject());

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

    private function contactDetails(): array
    {
        return ['name' => 'Ana', 'email' => 'ana@company.com', 'message' => 'Me gustaría conversar.'];
    }
}
