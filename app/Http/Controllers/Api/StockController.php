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
                'stocks.sucursal',
                'stocks.ubicacion',
            ])
            ->where('estado', 'activo');

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();
            $query->where(function (Builder $products) use ($search): void {
                $products
                    ->where('codigo', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhereRaw('CAST(especificaciones_extra AS CHAR) LIKE ?', ["%{$search}%"])
                    ->orWhereHas('atributos', function (Builder $attributes) use ($search): void {
                        $attributes->where('nombre_atributo', 'like', "%{$search}%")
                            ->orWhere('producto_atributo_valores.valor_texto', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(producto_atributo_valores.valor_numero AS CHAR) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($request->filled('categoria')) {
            $categoria = $request->string('categoria')->toString();
            $query->whereHas('categoria', function (Builder $categories) use ($categoria): void {
                $categories->where('nombre_categoria', 'like', "%{$categoria}%");
                if (ctype_digit($categoria)) {
                    $categories->orWhere('id_categoria', (int) $categoria);
                }
            });
        }

        if ($request->filled('sucursal')) {
            $sucursal = $request->string('sucursal')->toString();
            $query->whereHas('stocks.sucursal', function (Builder $branches) use ($sucursal): void {
                $branches->where('nombre_sucursal', 'like', "%{$sucursal}%");
                if (ctype_digit($sucursal)) {
                    $branches->orWhere('id_sucursal', (int) $sucursal);
                }
            });
        }

        if ($request->boolean('solo_disponibles')) {
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
