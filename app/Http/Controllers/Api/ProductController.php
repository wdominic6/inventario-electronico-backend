<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
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
        $query = Product::query()->with('category');

        if (method_exists(Product::class, 'stocks')) {
            $query->withSum('stocks', 'quantity');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return ProductResource::collection(
            $query->orderBy('name')->paginate($perPage)
        );
    }

    /**
     * GET /api/products/{product}
     */
    public function show(Product $product): ProductResource
    {
        $relations = ['category'];

        if (method_exists($product, 'stocks')) {
            $relations[] = 'stocks.branch';
            $relations[] = 'stocks.location';
        }

        $product->load($relations);

        return new ProductResource($product);
    }

    /**
     * POST /api/products
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;

        $product = Product::query()->create($data);
        $product->load('category');

        $this->auditService->log(
            action: 'product.created',
            entityType: 'Product',
            entityId: $product->id,
            description: 'Registro de componente electrónico '.$product->name.' (SKU: '.$product->sku.')',
            details: [
                'sku' => $product->sku,
                'name' => $product->name,
                'category_id' => $product->category_id,
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
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        $product->load('category');

        $this->auditService->log(
            action: 'product.updated',
            entityType: 'Product',
            entityId: $product->id,
            description: 'Actualización de componente electrónico '.$product->name.' (SKU: '.$product->sku.')',
            details: $request->validated(),
            request: $request
        );

        return new ProductResource($product);
    }

    /**
     * DELETE /api/products/{product}
     */
    public function destroy(Request $request, Product $product): Response|JsonResponse
    {
        if (method_exists($product, 'stocks') && $product->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el componente porque aún posee unidades en stock.',
            ], 409);
        }

        $productId = $product->id;
        $productSku = $product->sku;
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
