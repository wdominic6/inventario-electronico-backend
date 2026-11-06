<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBranchRequest;
use App\Http\Requests\Catalog\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\AuditService;
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
        $query = Branch::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        return BranchResource::collection(
            $query->orderBy('name')->get()
        );
    }

    /**
     * POST /api/branches
     */
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $branch = Branch::query()->create($data);

        $this->auditService->log(
            action: 'branch.created',
            entityType: 'Branch',
            entityId: $branch->id,
            description: 'Creación de sucursal '.$branch->name.' ('.$branch->code.')',
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
    public function update(UpdateBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->validated());

        $this->auditService->log(
            action: 'branch.updated',
            entityType: 'Branch',
            entityId: $branch->id,
            description: 'Actualización de sucursal '.$branch->name,
            details: $request->validated(),
            request: $request
        );

        return new BranchResource($branch);
    }

    /**
     * DELETE /api/branches/{branch}
     */
    public function destroy(Request $request, Branch $branch): Response|JsonResponse
    {
        if (method_exists($branch, 'stocks') && $branch->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la sucursal porque aún registra existencias de inventario activas.',
            ], 409);
        }

        $branchId = $branch->id;
        $branchName = $branch->name;
        $branch->delete();

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
