<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'ip_hash' => $this->ip_hash,
            'user_agent' => $this->user_agent,
            'severity' => $this->severity->value,
            'context_payload' => $this->context_payload,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
