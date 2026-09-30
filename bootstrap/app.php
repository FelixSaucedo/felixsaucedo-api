<?php

declare(strict_types=1);

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Support\SecurityAudit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['abilities' => CheckAbilities::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            app(SecurityAudit::class)->record($request, SecurityEvent::AuthFailure, SecuritySeverity::Medium);

            return response()->json(['message' => 'No autenticado.'], 401);
        });
    })->create();
