<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\SecurityAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SecurityAuditLog::class);
        $filters = $request->validate([
            'event_type' => ['sometimes', Rule::enum(SecurityEvent::class)],
            'severity' => ['sometimes', Rule::enum(SecuritySeverity::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = SecurityAuditLog::query()->latest('id');
        foreach (['event_type', 'severity'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return AuditLogResource::collection($query->paginate((int) ($filters['per_page'] ?? 25)))->response();
    }
}
