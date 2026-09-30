<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityAudit
{
    public function record(
        Request $request,
        SecurityEvent $event,
        SecuritySeverity $severity,
        array $fields = [],
    ): void {
        $allowedFields = array_values(array_intersect(
            ['name', 'email', 'subject', 'message', '_hp_company_url'],
            $fields,
        ));

        SecurityAuditLog::create([
            'event_type' => $event,
            'severity' => $severity,
            'ip_hash' => $this->ipHash($request),
            'user_agent' => $this->userAgent($request),
            'context_payload' => [
                'route' => $request->route()?->getName(),
                'fields' => $allowedFields,
            ],
        ]);
    }

    public function ipHash(Request $request): string
    {
        return hash('sha256', $request->ip() ?? '');
    }

    public function userAgent(Request $request): ?string
    {
        $agent = $request->userAgent();

        return $agent === null ? null : Str::substr(
            preg_replace('/[\\x00-\\x1F\\x7F]/u', '', strip_tags($agent)) ?? '',
            0,
            255,
        );
    }
}
