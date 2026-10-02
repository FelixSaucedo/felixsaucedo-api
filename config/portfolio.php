<?php

declare(strict_types=1);

return [
    'locales' => ['es', 'en'],
    'default_locale' => 'es',
    'admin' => [
        'name' => env('PORTFOLIO_ADMIN_NAME', 'Félix Saucedo'),
        'email' => env('PORTFOLIO_ADMIN_EMAIL'),
        'password' => env('PORTFOLIO_ADMIN_PASSWORD'),
    ],
    'token_minutes' => 60,
];
