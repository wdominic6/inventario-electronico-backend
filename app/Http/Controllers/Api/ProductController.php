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
        $product = Producto::query()->create([
            'id_categoria' => $data['category_id'],
            'id_fabricante' => $this->manufacturerId($data['brand']),
            'id_unidad' => $this->unitId($data['unit']),
            'codigo' => $data['sku'],
            'codigo_barras' => $data['barcode'],
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
        $specs = $data['specs'] ?? null;
        $product->update(array_filter([
            'id_categoria' => $data['category_id'] ?? null,
            'id_fabricante' => isset($data['brand']) ? $this->manufacturerId($data['brand']) : null,
            'id_unidad' => isset($data['unit']) ? $this->unitId($data['unit']) : null,
            'codigo' => $data['sku'] ?? null,
            'codigo_barras' => $data['barcode'] ?? null,
            'nombre' => $data['name'] ?? null,
            'marca' => $data['brand'] ?? null,
            'stock_minimo' => $data['min_stock'] ?? null,
            'costo_unitario' => $data['unit_cost'] ?? null,
            'precio_unitario' => $data['unit_price'] ?? null,
            'modelo' => $data['model'] ?? null,
            'encapsulado' => $specs['package_type'] ?? null,
            'voltaje_operacion' => $specs['voltage'] ?? null,
            'tolerancia' => $specs['tolerance'] ?? null,
            'especificaciones_extra' => $specs,
            'estado' => array_key_exists('is_active', $data) ? ($data['is_active'] ? 'activo' : 'inactivo') : null,
        ], static fn ($value): bool => $value !== null));
        $product->load(['categoria', 'fabricante', 'unidad']);

        $this->auditService->log(
            action: 'product.updated',
            entityType: 'Product',
            entityId: $product->id_producto,
            description: 'Actualización de componente electrónico '.$product->nombre.' (código: '.$product->codigo.')',
            details: $request->validated(),
            request: $request
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
        $record = UnidadMedida::query()
            ->where('nombre', $value)
            ->orWhere('abreviatura', $value)
            ->first();

        if ($record) {
            return (int) $record->id_unidad;
        }

        return (int) UnidadMedida::query()->create([
            'nombre' => $value,
            'abreviatura' => strtoupper(substr($value, 0, 10)),
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
        $product->delete();

        $this->auditService->log(
            action: 'product.deleted',
            entityType: 'Product',
            entityId: $productId,
            description: 'Eliminación de componente electrónico SKU '.$productSku,
            request: $request
        );

        return response()->noContent();
    }
}
