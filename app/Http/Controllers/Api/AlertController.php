<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\LowStockRequest;
use App\Http\Resources\LowStockResource;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AlertController extends Controller
{
    /**
     * GET /api/alerts/low-stock
     */
    public function lowStock(LowStockRequest $request): AnonymousResourceCollection
    {
        $query = $this->query();

        if ($request->filled('branch_id')) {
            $query->where('minimums.id_sucursal', $request->integer('branch_id'));
        }

        if ($request->filled('severity')) {
            $severity = $request->string('severity')->toString();
            $query->whereRaw(
                $severity === 'critical'
                    ? 'COALESCE(stock_totals.total_stock, 0) = 0'
                    : 'COALESCE(stock_totals.total_stock, 0) > 0'
            );
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return LowStockResource::collection(
            $query->orderByDesc('minimums.stock_minimo')->paginate($perPage)->withQueryString()
        );
    }

    private function query(): Builder
    {
        $stockTotals = DB::table('stocks')
            ->select('id_producto', 'id_sucursal', DB::raw('SUM(cantidad) AS total_stock'))
            ->groupBy('id_producto', 'id_sucursal');

        return DB::table('stock_minimos AS minimums')
            ->join('productos', 'productos.id_producto', '=', 'minimums.id_producto')
            ->join('sucursales', 'sucursales.id_sucursal', '=', 'minimums.id_sucursal')
            ->leftJoinSub($stockTotals, 'stock_totals', function ($join): void {
                $join->on('stock_totals.id_producto', '=', 'minimums.id_producto')
                    ->on('stock_totals.id_sucursal', '=', 'minimums.id_sucursal');
            })
            ->where('productos.estado', 'activo')
            ->whereRaw('COALESCE(stock_totals.total_stock, 0) <= minimums.stock_minimo')
            ->select([
                'productos.id_producto',
                'productos.codigo',
                'productos.nombre',
                'minimums.id_sucursal',
                'sucursales.nombre_sucursal',
                'minimums.stock_minimo',
                DB::raw('COALESCE(stock_totals.total_stock, 0) AS stock_actual'),
            ]);
    }
}
