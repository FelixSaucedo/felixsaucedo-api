<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = ['uuid', 'name', 'email', 'subject', 'message', 'ip_hash', 'user_agent', 'status'];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
        ];
    }
}
