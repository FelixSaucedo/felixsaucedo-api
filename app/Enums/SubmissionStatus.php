<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case Archived = 'archived';
    case Spam = 'spam';
}
