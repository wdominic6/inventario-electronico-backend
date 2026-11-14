<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Categoria;
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
        $query = Categoria::query();

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('nombre_categoria', 'like', "%{$search}%")
                    ->orWhere('prefijo_codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        return CategoryResource::collection(
            $query->orderBy('nombre_categoria')->get()
        );
    }

    /**
     * POST /api/categories
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Categoria::query()->create([
            'nombre_categoria' => $data['name'],
            'prefijo_codigo' => $data['code_prefix'],
            'descripcion' => $data['description'] ?? null,
        ]);

        $this->auditService->log(
            action: 'category.created',
            entityType: 'Category',
            entityId: $category->id_categoria,
            description: 'Creación de categoría '.$category->nombre_categoria,
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
    public function update(UpdateCategoryRequest $request, Categoria $category): CategoryResource
    {
        $data = $request->validated();
        $category->update([
            'nombre_categoria' => $data['name'] ?? $category->nombre_categoria,
            'prefijo_codigo' => $data['code_prefix'] ?? $category->prefijo_codigo,
            'descripcion' => $data['description'] ?? $category->descripcion,
        ]);

        $this->auditService->log(
            action: 'category.updated',
            entityType: 'Category',
            entityId: $category->id_categoria,
            description: 'Actualización de categoría '.$category->nombre_categoria,
            details: $request->validated(),
            request: $request
        );

        return new CategoryResource($category);
    }

    /**
     * DELETE /api/categories/{category}
     */
    public function destroy(Request $request, Categoria $category): Response|JsonResponse
    {
        if ($category->productos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene componentes electrónicos asociados.',
            ], 409);
        }

        $categoryId = $category->id_categoria;
        $categoryName = $category->nombre_categoria;
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
