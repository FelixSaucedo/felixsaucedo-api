<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SecurityEvent;
use App\Enums\UserRole;
use App\Models\CaseStudy;
use App\Models\ContactSubmission;
use App\Models\Section;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class PortfolioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Validator::fakeDnsLookups();
        config(['portfolio.admin.email' => null, 'portfolio.admin.password' => null,
            'portfolio.career.company' => null, 'portfolio.career.period' => null]);
    }

    protected function tearDown(): void
    {
        Validator::fakeDnsLookups(false);
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_honeypot_returns_success_without_validating_or_storing_contact_data(): void
    {
        $this->postJson('/api/v1/contact', ['_hp_company_url' => '<script>secret</script>'])
            ->assertStatus(202)->assertExactJson(['message' => 'Solicitud recibida.']);
        $this->assertDatabaseCount('contact_submissions', 0);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::HoneypotTriggered, $log->event_type);
        $this->assertSame('medium', $log->severity->value);
        $this->assertSame(hash('sha256', '127.0.0.1'), $log->ip_hash);
        $this->assertStringNotContainsString('secret', json_encode($log->context_payload));
    }

    public function test_contact_sanitizes_strings_and_never_accepts_client_owned_status_or_ip(): void
    {
        $this->withHeader('User-Agent', str_repeat('a', 400))
            ->postJson('/api/v1/contact', [
                ...$this->contactPayload(),
                'name' => '  <b>Félix</b>  ',
                'message' => ' <b>Necesito revisar una arquitectura de producción.</b> ',
                'status' => 'archived', 'ip_hash' => str_repeat('0', 64),
            ])->assertStatus(202)->assertExactJson(['message' => 'Solicitud recibida.']);
        $submission = ContactSubmission::query()->sole();
        $this->assertSame('Félix', $submission->name);
        $this->assertSame('Necesito revisar una arquitectura de producción.', $submission->message);
        $this->assertSame('new', $submission->status->value);
        $this->assertSame(hash('sha256', '127.0.0.1'), $submission->ip_hash);
        $this->assertSame(255, mb_strlen($submission->user_agent));
        $this->assertTrue(Str::isUuid($submission->uuid));
    }

    public function test_malicious_markup_is_logged_without_copying_the_message(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(),
            'message' => '<script>privateMarker</script> Necesito evaluar la arquitectura.',
        ])->assertStatus(202);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::MaliciousInputDetected, $log->event_type);
        $this->assertSame(['message'], $log->context_payload['fields']);
        $this->assertStringNotContainsString('privateMarker', json_encode($log->context_payload));
        $this->assertStringNotContainsString('<script>', ContactSubmission::query()->sole()->message);
    }

    public function test_invalid_email_and_short_message_are_rejected_after_sanitization(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(), 'email' => 'invalid', 'message' => '<b>corto</b>',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'message']);
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_reserved_email_domain_is_rejected_by_dns_validation(): void
    {
        $this->postJson('/api/v1/contact', [
            ...$this->contactPayload(), 'email' => 'felix@company.invalid',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_rate_limit_is_three_attempts_per_ip_per_ten_minutes(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
        }
        $this->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseHas('security_audit_logs', ['event_type' => 'rate_limit_exceeded']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
        $this->travel(11)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/v1/contact', ['_hp_company_url' => 'bot'])->assertStatus(202);
    }

    public function test_admin_endpoints_require_authentication_and_record_failures(): void
    {
        $this->getJson('/api/v1/admin/submissions')->assertUnauthorized();
        $this->getJson('/api/v1/admin/audit-logs')->assertUnauthorized();
        $this->assertDatabaseCount('security_audit_logs', 2);
        $this->assertDatabaseHas('security_audit_logs', ['event_type' => 'auth_failure']);
    }

    public function test_editor_is_denied_even_with_admin_token_abilities(): void
    {
        $editor = User::factory()->create(['role' => UserRole::Editor]);
        $token = $editor->createToken('test', ['admin:read', 'admin:write'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertForbidden();
        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertForbidden();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertForbidden();
        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_super_admin_can_read_submissions_and_logs_and_delete_by_uuid(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('test', ['admin:read', 'admin:write'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')
            ->assertOk()->assertJsonPath('data.0.uuid', $submission->uuid)->assertJsonMissingPath('data.0.ip_hash');
        $this->withToken($token)->getJson('/api/v1/admin/audit-logs')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/submissions/'.$submission->uuid)->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertNoContent();
        $this->assertDatabaseCount('contact_submissions', 0);
    }

    public function test_read_only_admin_token_cannot_delete_submissions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('read-only', ['admin:read'])->plainTextToken;
        $submission = $this->createSubmission();
        $this->withToken($token)->deleteJson('/api/v1/admin/submissions/'.$submission->uuid)->assertForbidden();
    }

    public function test_login_issues_expiring_hashed_token_and_logout_revokes_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin, 'password' => 'a-long-admin-password']);
        $response = $this->postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'a-long-admin-password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $token = $response->json('token');
        $storedToken = $admin->tokens()->sole();
        $this->assertNotSame($token, $storedToken->token);
        $this->assertNotNull($storedToken->expires_at);
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertUnauthorized();
    }

    public function test_expired_token_cannot_read_private_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $token = $admin->createToken('expired', ['admin:read'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/admin/submissions')->assertUnauthorized();
    }

    public function test_login_failure_is_generic_and_does_not_log_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'private-secret'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Credenciales inválidas.']);
        $log = SecurityAuditLog::query()->sole();
        $this->assertSame(SecurityEvent::AuthFailure, $log->event_type);
        $this->assertStringNotContainsString('private-secret', json_encode($log->context_payload));
        $this->assertStringNotContainsString('unknown', json_encode($log->context_payload));
    }

    public function test_portfolio_is_bilingual_excludes_inactive_sections_and_avoids_lazy_loading(): void
    {
        $this->seed();
        Section::query()->where('slug', 'leadership')->update(['is_active' => false]);
        Model::preventLazyLoading();
        DB::enableQueryLog();
        $this->getJson('/api/v1/portfolio?lang=en')->assertOk()
            ->assertJsonPath('lang', 'en')
            ->assertJsonPath('skill_categories.1.name', 'Databases')
            ->assertJsonPath('skill_categories.1.translations.name.es', 'Bases de datos')
            ->assertJsonPath('case_studies.0.title', 'Asynchronous ingestion vs. a synchronous monolith')
            ->assertJsonCount(2, 'sections')->assertJsonCount(0, 'career_milestones');
        $selects = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
        $this->assertLessThanOrEqual(7, count($selects));
        DB::disableQueryLog();
        $this->getJson('/api/v1/portfolio?lang=es')->assertOk()->assertJsonPath('skill_categories.1.name', 'Bases de datos');
        $this->getJson('/api/v1/portfolio?lang=fr')->assertUnprocessable();
    }

    public function test_seeder_is_idempotent_and_preserves_existing_admin_password(): void
    {
        config(['portfolio.admin.email' => 'felix@example.com', 'portfolio.admin.password' => 'initial-secure-password',
            'portfolio.career.company' => 'Empresa confirmada', 'portfolio.career.period' => '2020–2026']);
        $this->seed();
        config(['portfolio.admin.password' => 'another-secure-password']);
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('skill_categories', 4);
        $this->assertDatabaseCount('skills', 17);
        $this->assertDatabaseCount('case_studies', 2);
        $this->assertDatabaseCount('content_blocks', 8);
        $this->assertDatabaseCount('career_milestones', 1);
        $this->assertDatabaseCount('case_study_skill', 9);
        $admin = User::query()->sole();
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('initial-secure-password', $admin->password));
        $this->assertSame(45, CaseStudy::query()->where('slug', 'ingesta-asincrona-adtech')->sole()->impact_metrics['es']['p99_latency_ms']['after']);
    }

    private function contactPayload(): array
    {
        return ['name' => 'Félix', 'email' => 'felix@company.com', 'message' => 'Necesito revisar una arquitectura de producción.'];
    }

    private function createSubmission(): ContactSubmission
    {
        return ContactSubmission::query()->create([
            ...$this->contactPayload(), 'uuid' => (string) Str::uuid(), 'ip_hash' => hash('sha256', '127.0.0.1'),
        ]);
    }
}
