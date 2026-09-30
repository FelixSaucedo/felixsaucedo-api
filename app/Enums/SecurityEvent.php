<?php

declare(strict_types=1);

namespace App\Enums;

enum SecurityEvent: string
{
    case HoneypotTriggered = 'honeypot_triggered';
    case MaliciousInputDetected = 'malicious_input_detected';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case AuthFailure = 'auth_failure';
}
