<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SecurityAuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, SecurityAuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }
}
