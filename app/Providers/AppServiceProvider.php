<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Models\ContactSubmission;
use App\Models\SecurityAuditLog;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactSubmissionPolicy;
use App\Support\SecurityAudit;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(ContactSubmission::class, ContactSubmissionPolicy::class);
        Gate::policy(SecurityAuditLog::class, AuditLogPolicy::class);

        RateLimiter::for('contact', fn (Request $request): Limit => Limit::perMinutes(10, 3)
            ->by(app(SecurityAudit::class)->ipHash($request))
            ->response($this->rateLimitResponse(...)));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(app(SecurityAudit::class)->ipHash($request))
            ->response($this->rateLimitResponse(...)));
    }

    private function rateLimitResponse(Request $request, array $headers): JsonResponse
    {
        app(SecurityAudit::class)->record($request, SecurityEvent::RateLimitExceeded, SecuritySeverity::Medium);

        return response()->json(['message' => 'Demasiadas solicitudes. Intenta más tarde.'], 429, $headers);
    }
}
