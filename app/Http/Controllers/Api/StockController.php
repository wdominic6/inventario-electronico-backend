<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SearchStockRequest;
use App\Http\Resources\StockSearchResource;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    /**
     * GET /api/stock
     *
     * Retorna cada producto una sola vez, con el stock total y el desglose
     * por sucursal y ubicación.
     */
    public function buscarStock(SearchStockRequest $request): AnonymousResourceCollection
    {
        $query = Producto::query()
            ->with([
                'categoria',
                'fabricante',
                'unidad',
                'stocks.sucursal',
                'stocks.ubicacion',
            ])
            ->where('estado', 'activo');

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function (Builder $products) use ($search): void {
                $products
                    ->where('codigo', 'like', "%{$search}%")
                    ->orWhere('codigo_barras', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(especificaciones_extra AS CHAR) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('atributos', function (Builder $attributes) use ($search): void {
                        $attributes->where('nombre_atributo', 'like', "%{$search}%")
                            ->orWhere('producto_atributo_valores.valor_texto', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(producto_atributo_valores.valor_numero AS CHAR) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('category_id')) {
            $query->where('id_categoria', $request->integer('category_id'));
        }

        if ($request->filled('branch_id')) {
            $query->whereHas('stocks', function (Builder $stocks) use ($request): void {
                $stocks->where('id_sucursal', $request->integer('branch_id'));
            });
        }

        if ($request->filled('package_type')) {
            $query->where('encapsulado', 'like', '%'.$request->string('package_type')->toString().'%');
        }

        if ($request->filled('voltage')) {
            $query->where('voltaje_operacion', 'like', '%'.$request->string('voltage')->toString().'%');
        }

        if ($request->boolean('only_available')) {
            $query->whereHas('stocks', function (Builder $stocks): void {
                $stocks->where('cantidad', '>', 0);
            });
        }

        $perPage = $request->integer('per_page', 15);

        return StockSearchResource::collection(
            $query->orderBy('nombre')->paginate($perPage)->withQueryString()
        );
    }
}
