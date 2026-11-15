<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\KardexRequest;
use App\Http\Requests\Reports\RotationRequest;
use App\Http\Resources\DashboardSummaryResource;
use App\Http\Resources\KardexResource;
use App\Http\Resources\RotationResource;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /api/reports/dashboard-summary
     */
    public function dashboardSummary(): DashboardSummaryResource
    {
        $activeSkus = Producto::query()->where('estado', 'activo')->count();
        $inventoryValue = DB::table('stocks')
            ->join('productos', 'productos.id_producto', '=', 'stocks.id_producto')
            ->where('productos.estado', 'activo')
            ->selectRaw('COALESCE(SUM(stocks.cantidad * COALESCE(productos.costo_unitario, 0)), 0) AS total')
            ->value('total');

        $lowStockAlerts = $this->lowStockQuery()->count();
        $today = now()->startOfDay();
        $week = now()->startOfWeek();

        return new DashboardSummaryResource([
            'active_skus' => $activeSkus,
            'inventory_value' => $inventoryValue,
            'low_stock_alerts' => $lowStockAlerts,
            'movements_today' => DB::table('movimientos_kardex')->where('fecha_hora', '>=', $today)->count(),
            'movements_this_week' => DB::table('movimientos_kardex')->where('fecha_hora', '>=', $week)->count(),
            'operational_branches' => DB::table('sucursales')->where('estado', 'activa')->count(),
        ]);
    }

    /**
     * GET /api/reports/rotation
     */
    public function rotation(RotationRequest $request): AnonymousResourceCollection
    {
        [$from, $to] = $this->dateRange($request->validated());
        $query = DB::table('movimientos_kardex')
            ->join('productos', 'productos.id_producto', '=', 'movimientos_kardex.id_producto')
            ->leftJoin('categorias', 'categorias.id_categoria', '=', 'productos.id_categoria')
            ->whereBetween('movimientos_kardex.fecha_hora', [$from, $to])
            ->select([
                'productos.id_producto',
                'productos.codigo',
                'productos.nombre',
                'categorias.nombre_categoria',
            ])
            ->selectRaw('COUNT(*) AS movement_count')
            ->selectRaw('SUM(movimientos_kardex.cantidad) AS quantity_moved')
            ->groupBy('productos.id_producto', 'productos.codigo', 'productos.nombre', 'categorias.nombre_categoria')
            ->orderByDesc('quantity_moved');

        if ($request->filled('branch_id')) {
            $query->where('movimientos_kardex.id_sucursal', $request->integer('branch_id'));
        }

        return RotationResource::collection(
            $query->paginate(min(max($request->integer('per_page', 15), 1), 100))->withQueryString()
        );
    }

    /**
     * GET /api/reports/kardex/{product}
     */
    public function kardex(KardexRequest $request, Producto $product): AnonymousResourceCollection
    {
        $query = DB::table('movimientos_kardex')
            ->join('tipos_movimiento', 'tipos_movimiento.id_tipo_movimiento', '=', 'movimientos_kardex.id_tipo_movimiento')
            ->join('sucursales', 'sucursales.id_sucursal', '=', 'movimientos_kardex.id_sucursal')
            ->join('ubicaciones', function ($join): void {
                $join->on('ubicaciones.id_ubicacion', '=', 'movimientos_kardex.id_ubicacion')
                    ->on('ubicaciones.id_sucursal', '=', 'movimientos_kardex.id_sucursal');
            })
            ->join('usuarios', 'usuarios.id_usuario', '=', 'movimientos_kardex.id_usuario')
            ->where('movimientos_kardex.id_producto', $product->id_producto)
            ->select([
                'movimientos_kardex.id_movimiento_kardex',
                'tipos_movimiento.nombre_tipo',
                'tipos_movimiento.factor',
                'movimientos_kardex.cantidad',
                'movimientos_kardex.stock_anterior',
                'movimientos_kardex.stock_resultante',
                'movimientos_kardex.id_sucursal',
                'sucursales.nombre_sucursal',
                'movimientos_kardex.id_ubicacion',
                'ubicaciones.estante',
                'ubicaciones.nivel',
                'ubicaciones.casillero',
                'movimientos_kardex.id_usuario',
                'usuarios.nombre AS nombre_usuario',
                'usuarios.apellido AS apellido_usuario',
                'movimientos_kardex.observacion',
                'movimientos_kardex.fecha_hora',
            ])
            ->orderByDesc('movimientos_kardex.fecha_hora');

        $filters = $request->validated();
        if (isset($filters['branch_id'])) {
            $query->where('movimientos_kardex.id_sucursal', $filters['branch_id']);
        }
        if (isset($filters['date_from'])) {
            $query->where('movimientos_kardex.fecha_hora', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('movimientos_kardex.fecha_hora', '<=', $filters['date_to']);
        }

        return KardexResource::collection(
            $query->paginate(min(max($request->integer('per_page', 15), 1), 100))->withQueryString()
        );
    }

    private function lowStockQuery(): Builder
    {
        $stockTotals = DB::table('stocks')
            ->select('id_producto', 'id_sucursal', DB::raw('SUM(cantidad) AS total_stock'))
            ->groupBy('id_producto', 'id_sucursal');

        return DB::table('stock_minimos AS minimums')
            ->join('productos', 'productos.id_producto', '=', 'minimums.id_producto')
            ->leftJoinSub($stockTotals, 'stock_totals', function ($join): void {
                $join->on('stock_totals.id_producto', '=', 'minimums.id_producto')
                    ->on('stock_totals.id_sucursal', '=', 'minimums.id_sucursal');
            })
            ->where('productos.estado', 'activo')
            ->whereRaw('COALESCE(stock_totals.total_stock, 0) <= minimums.stock_minimo');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{Carbon, Carbon}
     */
    private function dateRange(array $filters): array
    {
        return [
            isset($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : now()->subDays(30)->startOfDay(),
            isset($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay(),
        ];
    }
}
