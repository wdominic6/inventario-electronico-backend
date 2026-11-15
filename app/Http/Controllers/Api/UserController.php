<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/users
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Usuario::query()->with(['rol', 'sucursal']);

        if ($request->filled('role')) {
            $roleName = $this->roleName($request->string('role')->toString());
            $query->whereHas('rol', fn ($roles) => $roles->where('nombre_rol', $roleName));
        }

        if ($request->filled('branch_id')) {
            $query->where('id_sucursal', $request->integer('branch_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('estado', $request->boolean('is_active') ? 'activo' : 'inactivo');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where(function ($nameQuery) use ($search): void {
                    $nameQuery->where('nombre', 'like', "%{$search}%")
                        ->orWhere('apellido', 'like', "%{$search}%");
                })
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return UserResource::collection(
            $query->orderBy('nombre')->paginate($perPage)
        );
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$nombre, $apellido] = array_pad(explode(' ', trim($data['name']), 2), 2, '');
        $user = Usuario::query()->create([
            'id_rol' => $this->roleId($data['role']),
            'id_sucursal' => $data['branch_id'],
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'estado' => ($data['is_active'] ?? true) ? 'activo' : 'inactivo',
        ]);
        $user->load(['rol', 'sucursal']);

        $this->auditService->log(
            action: 'user.created',
            entityType: 'User',
            entityId: $user->id_usuario,
            description: 'Creación de usuario '.$user->email,
            details: [
                'email' => $user->email,
                'role' => $data['role'],
            ],
            request: $request
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/users/{user}
     */
    public function update(UpdateUserRequest $request, Usuario $user): UserResource
    {
        $data = $request->validated();

        $user->update(array_filter([
            'email' => $data['email'] ?? null,
            'id_rol' => isset($data['role']) ? $this->roleId($data['role']) : null,
            'id_sucursal' => $data['branch_id'] ?? null,
            'nombre' => isset($data['name']) ? explode(' ', trim($data['name']), 2)[0] : null,
            'apellido' => isset($data['name']) ? (explode(' ', trim($data['name']), 2)[1] ?? '') : null,
            'password_hash' => ! empty($data['password']) ? Hash::make($data['password']) : null,
            'estado' => array_key_exists('is_active', $data) ? ($data['is_active'] ? 'activo' : 'inactivo') : null,
        ], static fn ($value): bool => $value !== null));
        $user->load(['rol', 'sucursal']);

        $this->auditService->log(
            action: 'user.updated',
            entityType: 'User',
            entityId: $user->id_usuario,
            description: 'Actualización de usuario '.$user->email,
            details: [
                'role' => $user->nombreRol(),
                'estado' => $user->estado,
            ],
            request: $request
        );

        return new UserResource($user);
    }

    private function roleId(string $role): int
    {
        return (int) Rol::query()
            ->where('nombre_rol', $this->roleName($role))
            ->firstOrFail()
            ->id_rol;
    }

    private function roleName(string $role): string
    {
        return match ($role) {
            'admin' => Rol::ADMINISTRADOR,
            'ventas' => Rol::VENTAS,
            'almacen' => Rol::ALMACEN,
            default => $role,
        };
    }
}
