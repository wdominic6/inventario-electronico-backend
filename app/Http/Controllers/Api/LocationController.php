<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreLocationRequest;
use App\Http\Requests\Catalog\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Ubicacion;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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

        if ($request->filled('is_active')) {
            $query->where('estado', $request->boolean('is_active') ? 'disponible' : 'inactiva');
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function ($subQuery) use ($search): void {
                $subQuery->where('codigo', 'like', "%{$search}%")
                    ->orWhere('estante', 'like', "%{$search}%")
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
            details: $data,
            request: $request
        );

        return (new LocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/locations/{location}
     */
    public function update(UpdateLocationRequest $request, Ubicacion $location): LocationResource|JsonResponse
    {
        $data = $request->validated();

        if (
            array_key_exists('branch_id', $data)
            && (int) $data['branch_id'] !== (int) $location->id_sucursal
            && ($location->stocks()->exists() || $this->hasHistoricalReferences($location->id_ubicacion))
        ) {
            return response()->json([
                'message' => 'No se puede cambiar la sucursal de esta ubicación porque tiene registros de stock o historial de movimientos asociados.',
            ], 409);
        }

        $before = [
            'branch_id' => $location->id_sucursal,
            'code' => $location->codigo,
            'shelf' => $location->estante,
            'level' => $location->nivel,
            'bin' => $location->casillero,
            'description' => $location->descripcion,
        ];

        $updates = [];
        if (array_key_exists('branch_id', $data)) {
            $updates['id_sucursal'] = $data['branch_id'];
        }
        if (array_key_exists('code', $data)) {
            $updates['codigo'] = $data['code'];
        }
        if (array_key_exists('shelf', $data)) {
            $updates['estante'] = $data['shelf'];
        }
        if (array_key_exists('level', $data)) {
            $updates['nivel'] = $data['level'];
        }
        if (array_key_exists('bin', $data)) {
            $updates['casillero'] = $data['bin'];
        }
        if (array_key_exists('description', $data)) {
            $updates['descripcion'] = $data['description'];
        }

        $location->update($updates);
        $location->load('sucursal');

        $this->auditService->log(
            action: 'location.updated',
            entityType: 'Location',
            entityId: $location->id_ubicacion,
            description: 'Actualización de ubicación física #'.$location->id_ubicacion,
            details: $data,
            request: $request,
            oldValues: $before
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
        $before = [
            'branch_id' => $location->id_sucursal,
            'code' => $location->codigo,
            'shelf' => $location->estante,
            'level' => $location->nivel,
            'bin' => $location->casillero,
            'estado' => $location->estado,
        ];

        $softDeactivated = DB::transaction(function () use ($location, $locationId): bool {
            if ($this->hasHistoricalReferences($locationId)) {
                $location->update(['estado' => 'inactiva']);

                return true;
            }

            $location->stocks()->where('cantidad', '<=', 0)->delete();
            $location->delete();

            return false;
        });

        $this->auditService->log(
            action: 'location.deleted',
            entityType: 'Location',
            entityId: $locationId,
            description: $softDeactivated
                ? 'Baja lógica de ubicación física '.$locationCode.' (posee historial transaccional)'
                : 'Eliminación de ubicación física '.$locationCode,
            request: $request,
            oldValues: $before
        );

        return response()->noContent();
    }

    private function hasHistoricalReferences(int $locationId): bool
    {
        return DB::table('movimientos_kardex')->where('id_ubicacion', $locationId)->exists()
            || DB::table('detalles_entrada')->where('id_ubicacion', $locationId)->exists()
            || DB::table('detalles_salida')->where('id_ubicacion', $locationId)->exists()
            || DB::table('detalles_ajuste')->where('id_ubicacion', $locationId)->exists()
            || DB::table('detalles_transferencia')
                ->where('id_ubicacion_origen', $locationId)
                ->orWhere('id_ubicacion_destino', $locationId)
                ->exists();
    }
}
