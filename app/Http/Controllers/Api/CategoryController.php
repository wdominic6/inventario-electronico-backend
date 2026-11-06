<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/categories
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query();

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('code_prefix', 'like', "%{$search}%");
            });
        }

        return CategoryResource::collection(
            $query->orderBy('name')->get()
        );
    }

    /**
     * POST /api/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        $this->auditService->log(
            action: 'category.created',
            entityType: 'Category',
            entityId: $category->id,
            description: 'Creación de categoría '.$category->name,
            details: $request->validated(),
            request: $request
        );

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/categories/{category}
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        $this->auditService->log(
            action: 'category.updated',
            entityType: 'Category',
            entityId: $category->id,
            description: 'Actualización de categoría '.$category->name,
            details: $request->validated(),
            request: $request
        );

        return new CategoryResource($category);
    }

    /**
     * DELETE /api/categories/{category}
     */
    public function destroy(Request $request, Category $category): Response|JsonResponse
    {
        if (method_exists($category, 'products') && $category->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene componentes electrónicos asociados.',
            ], 409);
        }

        $categoryId = $category->id;
        $categoryName = $category->name;
        $category->delete();

        $this->auditService->log(
            action: 'category.deleted',
            entityType: 'Category',
            entityId: $categoryId,
            description: 'Eliminación de categoría '.$categoryName,
            request: $request
        );

        return response()->noContent();
    }
}
