<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Fabricante;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/products
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Producto::query()->with(['categoria', 'fabricante', 'unidad']);

        $query->withSum('stocks', 'cantidad');

        if ($request->filled('category_id')) {
            $query->where('id_categoria', $request->integer('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('estado', $request->boolean('is_active') ? 'activo' : 'inactivo');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('encapsulado', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return ProductResource::collection(
            $query->orderBy('nombre')->paginate($perPage)
        );
    }

    /**
     * GET /api/products/{product}
     */
    public function show(Producto $product): ProductResource
    {
        $product->load(['categoria', 'fabricante', 'unidad', 'stocks.sucursal', 'stocks.ubicacion']);

        return new ProductResource($product);
    }

    /**
     * POST /api/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $specs = $data['specs'];

        $product = DB::transaction(function () use ($data, $specs): Producto {
            $product = Producto::query()->create([
                'id_categoria' => $data['category_id'],
                'id_fabricante' => $this->manufacturerId($data['brand']),
                'id_unidad' => $this->unitId($data['unit']),
                'codigo' => $data['sku'],
                'codigo_barras' => $data['barcode'] ?? null,
                'nombre' => $data['name'],
                'marca' => $data['brand'],
                'stock_minimo' => $data['min_stock'],
                'costo_unitario' => $data['unit_cost'],
                'precio_unitario' => $data['unit_price'],
                'modelo' => $data['model'] ?? null,
                'encapsulado' => $specs['package_type'] ?? null,
                'voltaje_operacion' => $specs['voltage'] ?? null,
                'tolerancia' => $specs['tolerance'] ?? null,
                'especificaciones_extra' => $specs,
                'estado' => ($data['is_active'] ?? true) ? 'activo' : 'inactivo',
            ]);
            $this->syncMinStock($product->id_producto, (int) $data['min_stock']);

            return $product;
        });

        $product->load(['categoria', 'fabricante', 'unidad']);

        $this->auditService->log(
            action: 'product.created',
            entityType: 'Product',
            entityId: $product->id_producto,
            description: 'Registro de componente electrónico '.$product->nombre.' (código: '.$product->codigo.')',
            details: [
                'codigo' => $product->codigo,
                'nombre' => $product->nombre,
                'id_categoria' => $product->id_categoria,
            ],
            request: $request
        );

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/products/{product}
     */
    public function update(UpdateProductRequest $request, Producto $product): ProductResource
    {
        $data = $request->validated();
        $before = [
            'sku' => $product->codigo,
            'barcode' => $product->codigo_barras,
            'name' => $product->nombre,
            'category_id' => $product->id_categoria,
            'brand' => $product->marca,
            'model' => $product->modelo,
            'min_stock' => (int) $product->stock_minimo,
            'unit_cost' => $product->costo_unitario !== null ? (float) $product->costo_unitario : null,
            'unit_price' => $product->precio_unitario !== null ? (float) $product->precio_unitario : null,
            'specs' => $product->especificaciones_extra,
            'estado' => $product->estado,
        ];

        $updates = [];
        if (array_key_exists('category_id', $data)) {
            $updates['id_categoria'] = $data['category_id'];
        }
        if (array_key_exists('brand', $data)) {
            $updates['id_fabricante'] = $this->manufacturerId($data['brand']);
            $updates['marca'] = $data['brand'];
        }
        if (array_key_exists('unit', $data)) {
            $updates['id_unidad'] = $this->unitId($data['unit']);
        }
        if (array_key_exists('sku', $data)) {
            $updates['codigo'] = $data['sku'];
        }
        if (array_key_exists('barcode', $data)) {
            $updates['codigo_barras'] = $data['barcode'];
        }
        if (array_key_exists('name', $data)) {
            $updates['nombre'] = $data['name'];
        }
        if (array_key_exists('min_stock', $data)) {
            $updates['stock_minimo'] = $data['min_stock'];
        }
        if (array_key_exists('unit_cost', $data)) {
            $updates['costo_unitario'] = $data['unit_cost'];
        }
        if (array_key_exists('unit_price', $data)) {
            $updates['precio_unitario'] = $data['unit_price'];
        }
        if (array_key_exists('model', $data)) {
            $updates['modelo'] = $data['model'];
        }
        if (array_key_exists('specs', $data)) {
            $specs = $data['specs'];
            $updates['encapsulado'] = $specs['package_type'] ?? null;
            $updates['voltaje_operacion'] = $specs['voltage'] ?? null;
            $updates['tolerancia'] = $specs['tolerance'] ?? null;
            $updates['especificaciones_extra'] = $specs;
        }
        if (array_key_exists('is_active', $data)) {
            $updates['estado'] = $data['is_active'] ? 'activo' : 'inactivo';
        }

        DB::transaction(function () use ($product, $updates, $data): void {
            $product->update($updates);
            if (array_key_exists('min_stock', $data)) {
                $this->syncMinStock($product->id_producto, (int) $data['min_stock']);
            }
        });

        $product->load(['categoria', 'fabricante', 'unidad']);

        $this->auditService->log(
            action: 'product.updated',
            entityType: 'Product',
            entityId: $product->id_producto,
            description: 'Actualización de componente electrónico '.$product->nombre.' (código: '.$product->codigo.')',
            details: $data,
            request: $request,
            oldValues: $before
        );

        return new ProductResource($product);
    }

    private function manufacturerId(string $brand): int
    {
        return (int) Fabricante::query()->firstOrCreate(
            ['nombre' => trim($brand)],
            ['pais_origen' => null]
        )->id_fabricante;
    }

    private function unitId(string $unit): int
    {
        $value = trim($unit);
        $abbr = mb_strtoupper(mb_substr($value, 0, 10, 'UTF-8'), 'UTF-8');

        $record = UnidadMedida::query()
            ->where('nombre', $value)
            ->orWhere('abreviatura', $value)
            ->orWhere('abreviatura', $abbr)
            ->first();

        if ($record) {
            return (int) $record->id_unidad;
        }

        return (int) UnidadMedida::query()->create([
            'nombre' => $value,
            'abreviatura' => $abbr,
        ])->id_unidad;
    }

    /**
     * DELETE /api/products/{product}
     */
    public function destroy(Request $request, Producto $product): Response|JsonResponse
    {
        if ($product->stocks()->where('cantidad', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el componente porque aún posee unidades en stock.',
            ], 409);
        }

        $productId = $product->id_producto;
        $productSku = $product->codigo;
        $before = [
            'sku' => $product->codigo,
            'name' => $product->nombre,
            'estado' => $product->estado,
        ];

        $softDeactivated = DB::transaction(function () use ($product, $productId): bool {
            if ($this->hasHistoricalReferences($productId)) {
                $product->update(['estado' => 'inactivo']);

                return true;
            }

            $product->stocks()->where('cantidad', '<=', 0)->delete();
            $product->delete();

            return false;
        });

        $description = $softDeactivated
            ? 'Baja lógica de componente electrónico SKU '.$productSku.' (posee historial transaccional)'
            : 'Eliminación de componente electrónico SKU '.$productSku;

        $this->auditService->log(
            action: 'product.deleted',
            entityType: 'Product',
            entityId: $productId,
            description: $description,
            request: $request,
            oldValues: $before
        );

        return response()->noContent();
    }

    private function hasHistoricalReferences(int $productId): bool
    {
        return DB::table('movimientos_kardex')->where('id_producto', $productId)->exists()
            || DB::table('detalles_entrada')->where('id_producto', $productId)->exists()
            || DB::table('detalles_salida')->where('id_producto', $productId)->exists()
            || DB::table('detalles_ajuste')->where('id_producto', $productId)->exists()
            || DB::table('detalles_transferencia')->where('id_producto', $productId)->exists();
    }

    private function syncMinStock(int $productId, int $minStock): void
    {
        $branchIds = DB::table('sucursales')->where('estado', 'activa')->pluck('id_sucursal');
        $now = now();

        foreach ($branchIds as $branchId) {
            DB::table('stock_minimos')->updateOrInsert(
                ['id_producto' => $productId, 'id_sucursal' => $branchId],
                ['stock_minimo' => $minStock, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }
}
