<?php

declare(strict_types=1);

return [
    'routes' => false,
    'stateful' => [],
    'guard' => [],
    'expiration' => 60,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
