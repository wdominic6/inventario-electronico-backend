<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBranchRequest;
use App\Http\Requests\Catalog\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Sucursal;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
        $validated = $request->validated();
        $payload = [
            'codigo' => $validated['code'],
            'nombre_sucursal' => $validated['name'],
            'direccion' => $validated['address'] ?? null,
            'telefono' => $validated['phone'] ?? null,
            'estado' => ($validated['is_active'] ?? true) ? 'activa' : 'inactiva',
        ];

        $branch = DB::transaction(function () use ($payload): Sucursal {
            $branch = Sucursal::query()->create($payload);

            if ($branch->estado === 'activa') {
                $this->initializeBranchMinStocks($branch->id_sucursal);
            }

            return $branch;
        });

        $this->auditService->log(
            action: 'branch.created',
            entityType: 'Branch',
            entityId: $branch->id_sucursal,
            description: 'Creación de sucursal '.$branch->nombre_sucursal,
            details: $payload,
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
        $before = [
            'code' => $branch->codigo,
            'name' => $branch->nombre_sucursal,
            'address' => $branch->direccion,
            'phone' => $branch->telefono,
            'estado' => $branch->estado,
        ];

        $updates = [];
        if (array_key_exists('code', $data)) {
            $updates['codigo'] = $data['code'];
        }
        if (array_key_exists('name', $data)) {
            $updates['nombre_sucursal'] = $data['name'];
        }
        if (array_key_exists('address', $data)) {
            $updates['direccion'] = $data['address'];
        }
        if (array_key_exists('phone', $data)) {
            $updates['telefono'] = $data['phone'];
        }
        if (array_key_exists('is_active', $data)) {
            $updates['estado'] = $data['is_active'] ? 'activa' : 'inactiva';
        }

        $branch->update($updates);

        $this->auditService->log(
            action: 'branch.updated',
            entityType: 'Branch',
            entityId: $branch->id_sucursal,
            description: 'Actualización de sucursal '.$branch->nombre_sucursal,
            details: $data,
            request: $request,
            oldValues: $before
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
        $before = [
            'code' => $branch->codigo,
            'name' => $branch->nombre_sucursal,
            'estado' => $branch->estado,
        ];

        $softDeactivated = DB::transaction(function () use ($branch, $branchId): bool {
            if ($this->hasHistoricalReferences($branchId)) {
                $branch->update(['estado' => 'inactiva']);

                return true;
            }

            $branch->stocks()->where('cantidad', '<=', 0)->delete();
            $branch->delete();

            return false;
        });

        $this->auditService->log(
            action: 'branch.deleted',
            entityType: 'Branch',
            entityId: $branchId,
            description: $softDeactivated
                ? 'Baja lógica de sucursal '.$branchName.' (posee historial transaccional)'
                : 'Eliminación de sucursal '.$branchName,
            request: $request,
            oldValues: $before
        );

        return response()->noContent();
    }

    private function initializeBranchMinStocks(int $branchId): void
    {
        $products = DB::table('productos')
            ->where('estado', 'activo')
            ->get(['id_producto', 'stock_minimo']);

        if ($products->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $products->map(static fn (object $product): array => [
            'id_producto' => (int) $product->id_producto,
            'id_sucursal' => $branchId,
            'stock_minimo' => (int) ($product->stock_minimo ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('stock_minimos')->insertOrIgnore($rows);
    }

    private function hasHistoricalReferences(int $branchId): bool
    {
        return DB::table('movimientos_kardex')->where('id_sucursal', $branchId)->exists()
            || DB::table('entradas_inventario')->where('id_sucursal', $branchId)->exists()
            || DB::table('salidas_inventario')->where('id_sucursal', $branchId)->exists()
            || DB::table('ajustes_inventario')->where('id_sucursal', $branchId)->exists()
            || DB::table('transferencias')
                ->where('id_sucursal_origen', $branchId)
                ->orWhere('id_sucursal_destino', $branchId)
                ->exists();
    }
}
