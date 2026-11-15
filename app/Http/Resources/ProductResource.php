<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_producto,
            'sku' => $this->codigo,
            'barcode' => $this->codigo_barras,
            'name' => $this->nombre,
            'category_id' => $this->id_categoria,
            'category' => new CategoryResource($this->whenLoaded('categoria')),
            'brand' => $this->marca ?? $this->fabricante?->nombre,
            'model' => $this->modelo,
            'unit' => $this->unidad?->nombre ?? $this->unidad?->abreviatura,
            'min_stock' => (int) $this->stock_minimo,
            'unit_cost' => $this->costo_unitario !== null ? (float) $this->costo_unitario : null,
            'unit_price' => $this->precio_unitario !== null ? (float) $this->precio_unitario : null,
            'specs' => $this->especificaciones_extra,
            'is_active' => $this->estado === 'activo',
            'total_stock' => $this->when(
                isset($this->stocks_sum_cantidad) || $this->relationLoaded('stocks'),
                fn () => isset($this->stocks_sum_cantidad)
                    ? (int) $this->stocks_sum_cantidad
                    : (int) $this->stocks->sum('cantidad')
            ),
            'stocks_by_branch' => $this->whenLoaded('stocks', function () {
                return $this->stocks
                    ->groupBy('id_sucursal')
                    ->map(function ($branchStocks) {
                        $first = $branchStocks->first();

                        return [
                            'branch_id' => $first->id_sucursal,
                            'branch_name' => $first->relationLoaded('sucursal') ? $first->sucursal?->nombre_sucursal : null,
                            'total_quantity' => (int) $branchStocks->sum('cantidad'),
                            'locations' => $branchStocks->map(fn ($stock) => [
                                'location_id' => $stock->id_ubicacion,
                                'shelf' => $stock->relationLoaded('ubicacion') ? $stock->ubicacion?->estante : null,
                                'level' => $stock->relationLoaded('ubicacion') ? $stock->ubicacion?->nivel : null,
                                'bin' => $stock->relationLoaded('ubicacion') ? $stock->ubicacion?->casillero : null,
                                'quantity' => (int) $stock->cantidad,
                            ])->values(),
                        ];
                    })
                    ->values();
            }),
        ];
    }
}
