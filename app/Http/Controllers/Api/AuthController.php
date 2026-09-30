<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SecurityEvent;
use App\Enums\SecuritySeverity;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, SecurityAudit $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $user = User::query()->where('email', mb_strtolower(trim($credentials['email'])))->first();
        $passwordHash = $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

        if (!Hash::check($credentials['password'], $passwordHash) || $user === null) {
            $audit->record($request, SecurityEvent::AuthFailure, SecuritySeverity::Medium);

            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        $abilities = $user->role === UserRole::SuperAdmin
            ? ['admin:read', 'admin:write']
            : ['content:read'];
        $expiresAt = now()->addMinutes((int) config('portfolio.token_minutes'));
        $token = $user->createToken('portfolio-admin', $abilities, $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => ['name' => $user->name, 'role' => $user->role->value],
        ])->header('Cache-Control', 'no-store');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
