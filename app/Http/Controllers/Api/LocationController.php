<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreLocationRequest;
use App\Http\Requests\Catalog\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use App\Services\AuditService;
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
        $query = Location::query()->with('branch');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('shelf', 'like', "%{$search}%")
                    ->orWhere('level', 'like', "%{$search}%")
                    ->orWhere('bin', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return LocationResource::collection(
            $query->orderBy('branch_id')->orderBy('code')->get()
        );
    }

    /**
     * POST /api/locations
     */
    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::query()->create($request->validated());
        $location->load('branch');

        $this->auditService->log(
            action: 'location.created',
            entityType: 'Location',
            entityId: $location->id,
            description: 'Creación de ubicación física '.$location->code,
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
    public function update(UpdateLocationRequest $request, Location $location): LocationResource
    {
        $location->update($request->validated());
        $location->load('branch');

        $this->auditService->log(
            action: 'location.updated',
            entityType: 'Location',
            entityId: $location->id,
            description: 'Actualización de ubicación física '.$location->code,
            details: $request->validated(),
            request: $request
        );

        return new LocationResource($location);
    }

    /**
     * DELETE /api/locations/{location}
     */
    public function destroy(Request $request, Location $location): Response|JsonResponse
    {
        if (method_exists($location, 'stocks') && $location->stocks()->where('quantity', '>', 0)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la ubicación física porque contiene stock disponible.',
            ], 409);
        }

        $locationId = $location->id;
        $locationCode = $location->code;
        $location->delete();

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
