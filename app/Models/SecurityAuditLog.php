<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use Illuminate\Database\Eloquent\Model;

class SecurityAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event_type', 'ip_hash', 'user_agent', 'severity', 'context_payload'];

    protected function casts(): array
    {
        return [
            'event_type' => SecurityEvent::class,
            'severity' => SecuritySeverity::class,
            'context_payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
