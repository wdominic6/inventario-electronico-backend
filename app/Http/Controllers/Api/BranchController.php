<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBranchRequest;
use App\Http\Requests\Catalog\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Sucursal;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/branches
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sucursal::query();

        if ($request->filled('is_active')) {
            $query->where('estado', $request->boolean('is_active') ? 'activa' : 'inactiva');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('nombre_sucursal', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('direccion', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        return BranchResource::collection(
            $query->orderBy('nombre_sucursal')->get()
        );
    }

    /**
     * POST /api/branches
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data = [
            'codigo' => $data['code'],
            'nombre_sucursal' => $data['name'],
            'direccion' => $data['address'] ?? null,
            'telefono' => $data['phone'] ?? null,
            'estado' => ($data['is_active'] ?? true) ? 'activa' : 'inactiva',
        ];

        $branch = Sucursal::query()->create($data);

        $this->auditService->log(
            action: 'branch.created',
            entityType: 'Branch',
            entityId: $branch->id_sucursal,
            description: 'Creación de sucursal '.$branch->nombre_sucursal,
            details: $data,
            request: $request
        );

        return (new BranchResource($branch))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/branches/{branch}
     */
    public function update(UpdateBranchRequest $request, Sucursal $branch): BranchResource
    {
        $data = $request->validated();
        $branch->update([
            'codigo' => $data['code'] ?? $branch->codigo,
            'nombre_sucursal' => $data['name'] ?? $branch->nombre_sucursal,
            'direccion' => $data['address'] ?? $branch->direccion,
            'telefono' => $data['phone'] ?? $branch->telefono,
            'estado' => array_key_exists('is_active', $data)
                ? ($data['is_active'] ? 'activa' : 'inactiva')
                : $branch->estado,
        ]);

        $this->auditService->log(
            action: 'branch.updated',
            entityType: 'Branch',
            entityId: $branch->id_sucursal,
            description: 'Actualización de sucursal '.$branch->nombre_sucursal,
            details: $request->validated(),
            request: $request
        );

        return new BranchResource($branch);
    }

    /**
     * DELETE /api/branches/{branch}
     */
    public function destroy(Request $request, Sucursal $branch): Response|JsonResponse
    {
        if ($branch->stocks()->where('cantidad', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la sucursal porque aún registra existencias de inventario activas.',
            ], 409);
        }

        $branchId = $branch->id_sucursal;
        $branchName = $branch->nombre_sucursal;

        try {
            $branch->stocks()->where('cantidad', '<=', 0)->delete();
            $branch->delete();
        } catch (QueryException) {
            $branch->update(['estado' => 'inactiva']);
        }

        $this->auditService->log(
            action: 'branch.deleted',
            entityType: 'Branch',
            entityId: $branchId,
            description: 'Eliminación de sucursal '.$branchName,
            request: $request
        );

        return response()->noContent();
    }
}
