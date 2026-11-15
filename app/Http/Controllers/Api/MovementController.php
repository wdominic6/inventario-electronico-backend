<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movement\StoreEntryMovementRequest;
use App\Http\Requests\Movement\StoreExitMovementRequest;
use App\Http\Requests\Movement\StoreTransferMovementRequest;
use App\Http\Resources\MovementResource;
use App\Services\StockTransactionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MovementController extends Controller
{
    public function __construct(
        protected StockTransactionService $stockTransactionService
    ) {}

    /**
     * GET /api/movements
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->baseQuery();

        if ($request->filled('branch_id')) {
            $query->where('movimientos_kardex.id_sucursal', $request->integer('branch_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('movimientos_kardex.id_producto', $request->integer('product_id'));
        }

        if ($request->filled('type')) {
            $type = $request->string('type')->toString();
            $mappedTypes = match (strtolower($type)) {
                'entry', 'entrada' => ['Entrada Compra', 'Ajuste Positivo'],
                'exit', 'salida' => ['Salida Venta', 'Ajuste Negativo'],
                'transfer', 'traslado', 'transferencia' => ['Traslado Saliente', 'Traslado Entrante'],
                default => [$type],
            };
            $query->whereIn('tipos_movimiento.nombre_tipo', $mappedTypes);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('movimientos_kardex.fecha_hora', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movimientos_kardex.fecha_hora', '<=', $request->string('date_to')->toString());
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return MovementResource::collection(
            $query->orderByDesc('movimientos_kardex.id_movimiento_kardex')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    /**
     * GET /api/movements/{movement}
     */
    public function show(int $movement): MovementResource|JsonResponse
    {
        $record = $this->baseQuery()
            ->where('movimientos_kardex.id_movimiento_kardex', $movement)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'El movimiento solicitado no existe.',
            ], 404);
        }

        return new MovementResource($record);
    }

    /**
     * POST /api/movements/entry
     */
    public function storeEntry(StoreEntryMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerEntry(
                $request->validated(),
                (int) $request->user()->id_usuario
            ),
        ], 201);
    }

    /**
     * POST /api/movements/exit
     */
    public function storeExit(StoreExitMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerExit(
                $request->validated(),
                (int) $request->user()->id_usuario
            ),
        ], 201);
    }

    /**
     * POST /api/movements/transfer
     */
    public function storeTransfer(StoreTransferMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerTransfer(
                $request->validated(),
                (int) $request->user()->id_usuario
            ),
        ], 201);
    }

    private function baseQuery(): Builder
    {
        return DB::table('movimientos_kardex')
            ->join('productos', 'productos.id_producto', '=', 'movimientos_kardex.id_producto')
            ->join('tipos_movimiento', 'tipos_movimiento.id_tipo_movimiento', '=', 'movimientos_kardex.id_tipo_movimiento')
            ->join('sucursales', 'sucursales.id_sucursal', '=', 'movimientos_kardex.id_sucursal')
            ->join('ubicaciones', function ($join): void {
                $join->on('ubicaciones.id_ubicacion', '=', 'movimientos_kardex.id_ubicacion')
                    ->on('ubicaciones.id_sucursal', '=', 'movimientos_kardex.id_sucursal');
            })
            ->join('usuarios', 'usuarios.id_usuario', '=', 'movimientos_kardex.id_usuario')
            ->select([
                'movimientos_kardex.id_movimiento_kardex',
                'movimientos_kardex.id_producto',
                'productos.codigo',
                'productos.nombre',
                'movimientos_kardex.id_sucursal',
                'sucursales.nombre_sucursal',
                'movimientos_kardex.id_ubicacion',
                'ubicaciones.estante',
                'ubicaciones.nivel',
                'ubicaciones.casillero',
                'movimientos_kardex.id_usuario',
                'usuarios.nombre as nombre_usuario',
                'usuarios.apellido as apellido_usuario',
                'usuarios.email as email_usuario',
                'tipos_movimiento.nombre_tipo',
                'tipos_movimiento.factor',
                'movimientos_kardex.cantidad',
                'movimientos_kardex.stock_anterior',
                'movimientos_kardex.stock_resultante',
                'movimientos_kardex.id_entrada',
                'movimientos_kardex.id_salida',
                'movimientos_kardex.id_ajuste',
                'movimientos_kardex.id_transferencia',
                'movimientos_kardex.observacion',
                'movimientos_kardex.fecha_hora',
            ]);
    }
}
