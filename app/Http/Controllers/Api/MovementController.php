<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movement\StoreEntryMovementRequest;
use App\Http\Requests\Movement\StoreExitMovementRequest;
use App\Http\Requests\Movement\StoreTransferMovementRequest;
use App\Services\StockTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovementController extends Controller
{
    public function __construct(
        protected StockTransactionService $stockTransactionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('movimientos_kardex')
            ->join('productos', 'productos.id_producto', '=', 'movimientos_kardex.id_producto')
            ->join('tipos_movimiento', 'tipos_movimiento.id_tipo_movimiento', '=', 'movimientos_kardex.id_tipo_movimiento')
            ->select([
                'movimientos_kardex.id_movimiento_kardex as id',
                'movimientos_kardex.id_producto',
                'productos.codigo',
                'productos.nombre',
                'movimientos_kardex.id_sucursal',
                'movimientos_kardex.id_ubicacion',
                'tipos_movimiento.nombre_tipo as type',
                'movimientos_kardex.cantidad',
                'movimientos_kardex.stock_anterior',
                'movimientos_kardex.stock_resultante',
                'movimientos_kardex.fecha_hora',
            ]);

        if ($request->filled('branch_id')) {
            $query->where('id_sucursal', $request->integer('branch_id'));
        }
        if ($request->filled('product_id')) {
            $query->where('id_producto', $request->integer('product_id'));
        }
        if ($request->filled('type')) {
            $query->where('tipos_movimiento.nombre_tipo', $request->string('type')->toString());
        }

        return response()->json($query->orderByDesc('fecha_hora')->paginate(min(max($request->integer('per_page', 15), 1), 100)));
    }

    public function show(int $movement): JsonResponse
    {
        $record = DB::table('movimientos_kardex')
            ->join('productos', 'productos.id_producto', '=', 'movimientos_kardex.id_producto')
            ->join('tipos_movimiento', 'tipos_movimiento.id_tipo_movimiento', '=', 'movimientos_kardex.id_tipo_movimiento')
            ->where('id_movimiento_kardex', $movement)
            ->select('movimientos_kardex.*', 'productos.codigo', 'productos.nombre', 'tipos_movimiento.nombre_tipo as type')
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function storeEntry(StoreEntryMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerEntry($request->validated(), (int) $request->user()->id_usuario),
        ], 201);
    }

    public function storeExit(StoreExitMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerExit($request->validated(), (int) $request->user()->id_usuario),
        ], 201);
    }

    public function storeTransfer(StoreTransferMovementRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->stockTransactionService->registerTransfer($request->validated(), (int) $request->user()->id_usuario),
        ], 201);
    }
}
