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

class AuthController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = Usuario::query()
            ->with(['rol', 'sucursal'])
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if ($user->estado !== 'activo') {
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
    public function me(Request $request): UserResource
    {
        $user = $request->user();
        $user->load(['rol', 'sucursal']);

        return new UserResource($user);
    }
}
