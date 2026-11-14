<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Verifica que el usuario autenticado esté activo y posea uno de los roles permitidos.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'No autenticado. Inicie sesión para continuar.',
            ], 401);
        }

        if ($user->estado !== 'activo') {
            return response()->json([
                'message' => 'Su cuenta de usuario se encuentra inactiva. Contacte al administrador.',
            ], 403);
        }

        $aliases = [
            'admin' => Rol::ADMINISTRADOR,
            'administrador' => Rol::ADMINISTRADOR,
            'ventas' => Rol::VENTAS,
            'almacen' => Rol::ALMACEN,
            'almacén' => Rol::ALMACEN,
        ];
        $rolesPermitidos = array_map(
            fn (string $role): string => $aliases[mb_strtolower($role)] ?? $role,
            $roles
        );

        if (! empty($rolesPermitidos) && ! in_array($user->nombreRol(), $rolesPermitidos, true)) {
            return response()->json([
                'message' => 'No tiene permisos suficientes para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
