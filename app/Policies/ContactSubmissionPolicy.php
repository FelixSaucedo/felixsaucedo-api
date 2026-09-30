<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::SuperAdmin;
    }

    public function view(User $user, ContactSubmission $submission): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, ContactSubmission $submission): bool
    {
        return $this->viewAny($user);
    }
}
