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
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpFoundation\Response;
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
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api', 'api/*') ? null : '/',
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api', 'api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api', 'api/*')) {
                return $response;
            }

            $status = $response->getStatusCode();
            $message = match ($status) {
                404 => 'Resource not found',
                405 => 'Method not allowed',
                default => $status >= 500 ? 'Server error' : null,
            };
            if ($message === null) {
                return $response;
            }

            $headers = [];
            foreach (['Allow', 'Retry-After'] as $header) {
                if ($response->headers->has($header)) {
                    $headers[$header] = $response->headers->get($header);
                }
            }

            return response()->json(['message' => $message], $status, $headers);
        });
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request): ?JsonResponse {
            if (!$request->routeIs('api.v1.contact')) {
                return null;
            }

            app(SecurityAudit::class)->record($request, SecurityEvent::RateLimitExceeded, SecuritySeverity::Medium);

            return response()->json(['message' => 'Demasiadas solicitudes. Intenta más tarde.'], 429, $exception->getHeaders());
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (!$request->is('api', 'api/*')) {
                return null;
            }

            app(SecurityAudit::class)->record($request, SecurityEvent::AuthFailure, SecuritySeverity::Medium);

            return response()->json(['message' => 'No autenticado.'], 401);
        });
    })->create();
