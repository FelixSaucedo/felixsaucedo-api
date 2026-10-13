<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\ContactSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApiHttpContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();
    }

    protected function tearDown(): void
    {
        Validator::fakeDnsLookups(false);
        parent::tearDown();
    }

    #[DataProvider('unknownApiPaths')]
    public function test_unknown_api_paths_return_json_404_without_an_accept_header(string $path): void
    {
        $this->get($path)->assertNotFound()->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Resource not found']);
    }

    public static function unknownApiPaths(): array
    {
        return [['/api'], ['/api/unknown'], ['/api/v1/unknown']];
    }

    public function test_unsupported_api_method_returns_json_405_and_allow_header(): void
    {
        $this->get('/api/v1/contact')->assertStatus(405)->assertHeader('Allow', 'POST')
            ->assertHeader('Content-Type', 'application/json')->assertExactJson(['message' => 'Method not allowed']);
    }

    public function test_private_api_returns_json_401_without_an_accept_header(): void
    {
        $this->get('/api/v1/admin/submissions')->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')->assertExactJson(['message' => 'No autenticado.']);
    }

    public function test_production_server_errors_never_expose_exception_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/api/test-error', fn () => throw new RuntimeException('SMTP password=private-secret at /var/www/html/.env'));

        $this->get('/api/test-error')->assertInternalServerError()->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Server error']);
    }

    public function test_api_errors_are_safe_even_if_debug_is_accidentally_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('/api/test-error', fn () => throw new RuntimeException('private-secret at /var/www/html/.env'));

        $this->getJson('/api/test-error')->assertInternalServerError()->assertExactJson(['message' => 'Server error']);
    }

    public function test_header_injection_and_control_characters_are_sanitized_or_rejected(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/contact', [
            'name' => " Ana\x00\r\nBcc: victim@company.com ",
            'email' => 'ana@company.com',
            'subject' => "Hello\x01\nReply-To: victim@company.com",
            'message' => "Safe\x00 message\nSecond line",
        ])->assertCreated();

        $contact = Contact::query()->sole();
        $this->assertSame('Ana Bcc: victim@company.com', $contact->name);
        $this->assertSame('Hello Reply-To: victim@company.com', $contact->subject);
        $this->assertSame("Safe message\nSecond line", $contact->message);
        Mail::assertQueuedCount(2);

        $this->postJson('/api/v1/contact', [
            'name' => 'Ana', 'email' => "ana@company.com\r\nBcc: victim@company.com", 'message' => 'Hello',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_new_contact_is_visible_and_deletable_through_the_protected_admin_api(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('test', ['admin:read', 'admin:write'])->plainTextToken;

        $this->postJson('/api/v1/contact', [
            'name' => 'Ana', 'email' => 'ana@company.com', 'message' => 'A contact to review',
        ])->assertCreated();

        $submission = ContactSubmission::query()->sole();
        $this->assertSame(Contact::query()->sole()->id, $submission->contact_id);
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertOk()
            ->assertJsonPath('data.0.name', 'Ana')->assertJsonMissingPath('data.0.ip_hash');
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertNoContent();
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_audit_storage_failure_does_not_turn_honeypot_rejection_into_a_server_error(): void
    {
        Mail::fake();
        DB::unprepared("CREATE TRIGGER reject_audit BEFORE INSERT ON security_audit_logs BEGIN SELECT RAISE(FAIL, 'Audit unavailable'); END");

        $this->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertUnprocessable()
            ->assertJsonValidationErrors('_hp_company_url');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('security_audit_logs', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_changing_forwarded_ip_headers_cannot_bypass_the_contact_rate_limit(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withHeader('X-Forwarded-For', '192.0.2.'.$attempt)
                ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertUnprocessable();
        }

        $this->withHeader('X-Forwarded-For', '192.0.2.99')
            ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_legacy_contacts_are_backfilled_for_admin_without_resending_mail(): void
    {
        $migration = require database_path('migrations/2026_10_12_233106_add_contact_dispatch_and_admin_tracking.php');
        $migration->down();
        $contact = Contact::factory()->create();

        $migration->up();

        $this->assertDatabaseHas('contact_submissions', ['contact_id' => $contact->id, 'email' => $contact->email]);
        $this->assertNotNull($contact->refresh()->mail_queued_at);
        $this->artisan('contacts:dispatch-pending')->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
    }
}
