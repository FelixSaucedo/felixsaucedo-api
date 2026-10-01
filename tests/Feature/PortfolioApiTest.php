<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SecurityEvent;
use App\Enums\UserRole;
use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\ContactSubmission;
use App\Models\ContentBlock;
use App\Models\Section;
use App\Models\SecurityAuditLog;
use App\Models\Skill;
use App\Models\SkillCategory;
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
        config(['portfolio.admin.email' => null, 'portfolio.admin.password' => null]);
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
            ->assertJsonPath('categories.1.name', 'Data & Cache')
            ->assertJsonPath('case_studies.0.title', 'Async Event Processing vs. Synchronous Monolith (AdTech)')
            ->assertJsonPath('case_studies.0.badge_text', 'AWS Lambda + SQS + Redis')
            ->assertJsonCount(0, 'leadership')->assertJsonCount(3, 'career')
            ->assertJsonCount(5, 'categories')->assertJsonCount(33, 'skills')
            ->assertJsonCount(4, 'philosophies')
            ->assertJsonPath('career.0.period', '2019 — 2026')
            ->assertJsonPath('career.0.company', 'Minga Digital · Remoto')
            ->assertJsonPath('hero.title', 'Code is the vehicle; solving real business bottlenecks is the goal.')
            ->assertJsonPath('hero.badge', 'Senior Software Engineer & Hands-on Lead')
            ->assertJsonPath('skills.0.subtitle', 'Laravel · Phalcon · Cake')
            ->assertJsonPath('skills.0.category_slug', 'backend')
            ->assertJsonPath('skills.32.accent_color', '#94a3b8')
            ->assertJsonPath('skills.29.accent_color', '#fbbf24')
            ->assertJsonPath('categories.4.default_accent_color', '#fbbf24')
            ->assertJsonPath('case_studies.1.badge_color_hex', '#10b981')
            ->assertJsonPath('career.2.accent_color_hex', '#c084fc')
            ->assertJsonPath('philosophies.0.accent_color_hex', '#38bdf8');
        $selects = array_filter(DB::getQueryLog(), fn (array $query): bool => str_starts_with(strtolower($query['query']), 'select'));
        $this->assertLessThanOrEqual(7, count($selects));
        DB::disableQueryLog();
        $this->getJson('/api/v1/portfolio?lang=es')->assertOk()
            ->assertJsonPath('categories.1.name', 'Datos & Caché')
            ->assertJsonPath('career.0.period', '2019 — 2026')
            ->assertJsonPath('career.2.role', 'Full Stack Developer')
            ->assertJsonPath('hero.title', 'El código es el medio; resolver cuellos de botella reales es el fin.')
            ->assertJsonPath('hero.badge', 'Senior Software Engineer & Lead Hands-on');
        $this->getJson('/api/v1/portfolio')->assertOk()
            ->assertJsonPath('categories.1.name', 'Datos & Caché');
        $this->getJson('/api/v1/portfolio?lang=fr')->assertUnprocessable();
    }

    public function test_seeder_is_idempotent_and_preserves_existing_admin_password(): void
    {
        config(['portfolio.admin.email' => 'felix@example.com', 'portfolio.admin.password' => 'initial-secure-password']);
        $this->seed();
        config(['portfolio.admin.password' => 'another-secure-password']);
        $this->seed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('skill_categories', 5);
        $this->assertDatabaseCount('skills', 33);
        $this->assertDatabaseCount('case_studies', 2);
        $this->assertDatabaseCount('content_blocks', 8);
        $this->assertDatabaseCount('career_milestones', 3);
        $this->assertDatabaseCount('case_study_skill', 0);
        $admin = User::query()->sole();
        $this->assertSame(UserRole::SuperAdmin, $admin->role);
        $this->assertTrue(Hash::check('initial-secure-password', $admin->password));
        $this->assertNull(CaseStudy::query()->where('slug', 'async-event-processing')->sole()->impact_metrics);
        $this->assertSame(['>_', '~%', '{ }', '</>'], ContentBlock::query()->whereHas('section', fn ($query) => $query->where('slug', 'philosophy'))->orderBy('order')->pluck('icon')->all());
    }

    public function test_portfolio_returns_database_edits_without_seeded_fallbacks(): void
    {
        $this->seed();
        ContentBlock::query()->where('slug', 'hero')->sole()->update([
            'title' => ['es' => 'Título editado', 'en' => 'Edited title'],
            'subtitle' => ['es' => 'Insignia editada', 'en' => 'Edited badge'],
        ]);
        Skill::query()->where('slug', 'php')->sole()->update([
            'subtitle' => ['es' => 'Subtítulo editado', 'en' => 'Edited subtitle'],
            'accent_color' => '#ef4444',
        ]);
        CareerMilestone::query()->where('order', 1)->sole()->update(['company' => 'Empresa editada', 'accent_color_hex' => '#fedcba']);
        SkillCategory::query()->where('slug', 'backend')->sole()->update(['default_accent_color' => '#123456']);
        ContentBlock::query()->where('slug', 'forensic-debugging')->sole()->update(['accent_color_hex' => '#654321']);
        CaseStudy::query()->where('slug', 'async-event-processing')->sole()->update(['badge_color_hex' => '#abcdef']);

        $this->getJson('/api/v1/portfolio?lang=en')->assertOk()
            ->assertJsonPath('hero.title', 'Edited title')
            ->assertJsonPath('hero.badge', 'Edited badge')
            ->assertJsonPath('skills.0.subtitle', 'Edited subtitle')
            ->assertJsonPath('skills.0.accent_color', '#ef4444')
            ->assertJsonPath('categories.0.default_accent_color', '#123456')
            ->assertJsonPath('philosophies.0.accent_color_hex', '#654321')
            ->assertJsonPath('case_studies.0.badge_color_hex', '#abcdef')
            ->assertJsonPath('career.0.accent_color_hex', '#fedcba')
            ->assertJsonPath('career.0.company', 'Empresa editada');
        Section::query()->where('slug', 'hero')->update(['is_active' => false]);
        $this->getJson('/api/v1/portfolio')->assertOk()->assertJsonPath('hero', null);
    }

    public function test_color_migration_converts_existing_css_and_preserves_custom_hex_values(): void
    {
        $this->seed();
        $migration = require database_path('migrations/2026_10_01_014150_replace_portfolio_css_colors_with_hex_values.php');
        $migration->down();
        DB::table('skills')->where('slug', 'php')->update(['badge_color' => 'text-sky-600 dark:text-brand-accent']);
        DB::table('skills')->where('slug', 'typescript')->update(['badge_color' => '#A1B2C3']);
        DB::table('skills')->where('slug', 'expressjs')->update(['badge_color' => null]);
        DB::table('content_blocks')->where('slug', 'semantic-clarity')->update(['badge_bg' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400']);
        DB::table('case_studies')->where('slug', 'database-query-tuning')->update(['badge_color' => 'bg-emerald-500/10 text-emerald-700 dark:text-brand-emerald border border-emerald-500/20']);
        DB::table('career_milestones')->where('order', 3)->update(['badge_color' => 'text-purple-600 dark:text-purple-400']);

        $migration->up();

        $this->getJson('/api/v1/portfolio?lang=en')->assertOk()
            ->assertJsonPath('skills.0.accent_color', '#38bdf8')
            ->assertJsonPath('skills.1.accent_color', '#a1b2c3')
            ->assertJsonPath('skills.3.accent_color', '#94a3b8')
            ->assertJsonPath('categories.1.default_accent_color', '#10b981')
            ->assertJsonPath('philosophies.2.accent_color_hex', '#c084fc')
            ->assertJsonPath('case_studies.1.badge_color_hex', '#10b981')
            ->assertJsonPath('career.2.accent_color_hex', '#c084fc');
        $this->assertDatabaseCount('skills', 33);
        $migration->down();
        $migration->up();
        $this->assertSame('#a1b2c3', Skill::query()->where('slug', 'typescript')->sole()->accent_color);
    }

    public function test_period_migration_preserves_legacy_values_and_rolls_back_to_spanish(): void
    {
        $migration = require database_path('migrations/2026_09_30_230132_localize_career_milestone_period.php');
        $migration->down();
        $period = str_repeat('x', 250);
        DB::table('career_milestones')->insert([
            'period' => $period, 'role' => json_encode(['es' => 'Rol', 'en' => 'Role']),
            'company' => 'Legacy company', 'order' => 1,
        ]);

        $migration->up();

        $milestone = CareerMilestone::query()->sole();
        $this->assertSame(['es' => $period, 'en' => $period], $milestone->period);
        $migration->down();
        $this->assertSame($period, DB::table('career_milestones')->sole()->period);
        $migration->up();
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
