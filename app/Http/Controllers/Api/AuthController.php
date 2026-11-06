<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Las credenciales proporcionadas son incorrectas.',
            ], 401);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            return response()->json([
                'message' => 'Esta cuenta de usuario se encuentra desactivada.',
            ], 403);
        }

        if (method_exists($user, 'branch')) {
            $user->load('branch');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->auditService->log(
            action: 'auth.login',
            entityType: 'User',
            entityId: $user->id,
            description: 'Inicio de sesión exitoso de '.$user->email,
            userId: $user->id,
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

        if ($user) {
            $this->auditService->log(
                action: 'auth.logout',
                entityType: 'User',
                entityId: $user->id,
                description: 'Cierre de sesión de '.$user->email,
                userId: $user->id,
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

        if ($user && method_exists($user, 'branch')) {
            $user->load('branch');
        }

        return new UserResource($user);
    }
}
