<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\Usuario;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Intentos fallidos permitidos por combinación email + IP antes de bloquear temporalmente.
     */
    public const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Segundos de bloqueo tras superar el máximo de intentos fallidos.
     */
    public const LOGIN_DECAY_SECONDS = 60;

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $throttleKey = $this->throttleKey($credentials['email'], (string) $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => "Demasiados intentos de inicio de sesión. Intente nuevamente en {$seconds} segundos.",
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $user = Usuario::query()
            ->with(['rol', 'sucursal'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            if ($user) {
                $this->auditService->log(
                    action: 'auth.login_failed',
                    entityType: 'Usuario',
                    entityId: $user->id_usuario,
                    description: 'Intento fallido de inicio de sesión para '.$user->email,
                    userId: $user->id_usuario,
                    request: $request
                );
            }

            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        if ($user->estado !== 'activo') {
            $this->auditService->log(
                action: 'auth.login_blocked',
                entityType: 'Usuario',
                entityId: $user->id_usuario,
                description: 'Intento de acceso bloqueado para cuenta inactiva '.$user->email,
                userId: $user->id_usuario,
                request: $request
            );

            return response()->json([
                'message' => 'Esta cuenta de usuario se encuentra desactivada.',
            ], 403);
        }

        $user->forceFill(['ultimo_acceso' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->log(
            action: 'auth.login',
            entityType: 'Usuario',
            entityId: $user->id_usuario,
            description: 'Inicio de sesión exitoso de '.$user->email,
            userId: $user->id_usuario,
            request: $request
        );

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => (new UserResource($user))->resolve($request),
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): Response
    {
        $user = $request->user();

        if ($user instanceof Usuario) {
            $this->auditService->log(
                action: 'auth.logout',
                entityType: 'Usuario',
                entityId: $user->id_usuario,
                description: 'Cierre de sesión de '.$user->email,
                userId: $user->id_usuario,
                request: $request
            );

            $user->currentAccessToken()?->delete();
        }

        return response()->noContent();
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): UserResource|JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof Usuario) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        if ($user->estado !== 'activo') {
            return response()->json([
                'message' => 'Su cuenta de usuario se encuentra inactiva. Contacte al administrador.',
            ], 403);
        }

        $user->load(['rol', 'sucursal']);

        return new UserResource($user);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'login:'.Str::transliterate(Str::lower(trim($email))).'|'.$ip;
    }
}
