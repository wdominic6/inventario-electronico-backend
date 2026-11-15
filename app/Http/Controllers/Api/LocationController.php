<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreLocationRequest;
use App\Http\Requests\Catalog\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Ubicacion;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/locations
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Ubicacion::query()->with('sucursal');

        if ($request->filled('branch_id')) {
            $query->where('id_sucursal', $request->integer('branch_id'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('estante', 'like', "%{$search}%")
                    ->orWhere('nivel', 'like', "%{$search}%")
                    ->orWhere('casillero', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        return LocationResource::collection(
            $query->orderBy('id_sucursal')->orderBy('estante')->get()
        );
    }

    /**
     * POST /api/locations
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $location = Ubicacion::query()->create([
            'id_sucursal' => $data['branch_id'],
            'codigo' => $data['code'],
            'estante' => $data['shelf'],
            'nivel' => $data['level'],
            'casillero' => $data['bin'],
            'estado' => 'disponible',
            'descripcion' => $data['description'] ?? null,
        ]);
        $location->load('sucursal');

        $this->auditService->log(
            action: 'location.created',
            entityType: 'Location',
            entityId: $location->id_ubicacion,
            description: 'Creación de ubicación física #'.$location->id_ubicacion,
            details: $request->validated(),
            request: $request
        );

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/locations/{location}
     */
    public function update(UpdateLocationRequest $request, Ubicacion $location): LocationResource
    {
        $data = $request->validated();
        $location->update([
            'id_sucursal' => $data['branch_id'] ?? $location->id_sucursal,
            'codigo' => $data['code'] ?? $location->codigo,
            'estante' => $data['shelf'] ?? $location->estante,
            'nivel' => $data['level'] ?? $location->nivel,
            'casillero' => $data['bin'] ?? $location->casillero,
            'descripcion' => $data['description'] ?? $location->descripcion,
        ]);
        $location->load('sucursal');

        $this->auditService->log(
            action: 'location.updated',
            entityType: 'Location',
            entityId: $location->id_ubicacion,
            description: 'Actualización de ubicación física #'.$location->id_ubicacion,
            details: $request->validated(),
            request: $request
        );

        return new LocationResource($location);
    }

    /**
     * DELETE /api/locations/{location}
     */
    public function destroy(Request $request, Ubicacion $location): Response|JsonResponse
    {
        if ($location->stocks()->where('cantidad', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la ubicación física porque contiene stock disponible.',
            ], 409);
        }

        $locationId = $location->id_ubicacion;
        $locationCode = $location->codigo;

        try {
            $location->stocks()->where('cantidad', '<=', 0)->delete();
            $location->delete();
        } catch (QueryException) {
            $location->update(['estado' => 'inactiva']);
        }

        $this->auditService->log(
            action: 'location.deleted',
            entityType: 'Location',
            entityId: $locationId,
            description: 'Eliminación de ubicación física '.$locationCode,
            request: $request
        );

        return response()->noContent();
    }
}
