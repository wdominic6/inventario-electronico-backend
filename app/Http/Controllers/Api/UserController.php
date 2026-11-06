<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        $query = User::query();

        if (method_exists(User::class, 'branch')) {
            $query->with('branch');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return UserResource::collection(
            $query->orderBy('name')->paginate($perPage)
        );
    }

    /**
     * POST /api/users
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::query()->create($data);

        if (method_exists($user, 'branch')) {
            $user->load('branch');
        }

        $this->auditService->log(
            action: 'user.created',
            entityType: 'User',
            entityId: $user->id,
            description: 'Creación de usuario '.$user->email.' con rol '.$user->role,
            details: [
                'email' => $user->email,
                'role' => $user->role,
                'branch_id' => $user->branch_id,
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
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (array_key_exists('password', $data) && empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        if (method_exists($user, 'branch')) {
            $user->load('branch');
        }

        $this->auditService->log(
            action: 'user.updated',
            entityType: 'User',
            entityId: $user->id,
            description: 'Actualización de usuario '.$user->email,
            details: [
                'role' => $user->role,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
            ],
            request: $request
        );

        return new UserResource($user);
    }
}
