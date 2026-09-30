<?php

declare(strict_types=1);

return [
    'admin' => [
        'name' => env('PORTFOLIO_ADMIN_NAME', 'Félix Saucedo'),
        'email' => env('PORTFOLIO_ADMIN_EMAIL'),
        'password' => env('PORTFOLIO_ADMIN_PASSWORD'),
    ],
    'career' => [
        'company' => env('PORTFOLIO_CAREER_COMPANY'),
        'period' => env('PORTFOLIO_CAREER_PERIOD'),
        'location' => env('PORTFOLIO_CAREER_LOCATION'),
    ],
    'token_minutes' => 60,
];
