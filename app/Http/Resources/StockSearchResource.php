<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockSearchResource extends JsonResource
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
            'brand' => $this->marca ?? $this->fabricante?->nombre,
            'model' => $this->modelo,
            'unit' => $this->unidad?->nombre ?? $this->unidad?->abreviatura,
            'min_stock' => (int) $this->stock_minimo,
            'package_type' => $this->encapsulado,
            'voltage' => $this->voltaje_operacion,
            'tolerance' => $this->tolerancia,
            'specs' => $this->especificaciones_extra,
            'category' => [
                'id' => $this->categoria?->id_categoria,
                'name' => $this->categoria?->nombre_categoria,
            ],
            'total_stock' => (int) $this->stocks->sum('cantidad'),
            'stocks_by_branch' => $this->stocks
                ->groupBy('id_sucursal')
                ->map(function ($stocks): array {
                    $primero = $stocks->first();

                    return [
                        'branch_id' => $primero->id_sucursal,
                        'branch_name' => $primero->sucursal?->nombre_sucursal,
                        'total_quantity' => (int) $stocks->sum('cantidad'),
                        'locations' => $stocks->map(fn ($stock): array => [
                            'location_id' => $stock->id_ubicacion,
                            'shelf' => $stock->ubicacion?->estante,
                            'level' => $stock->ubicacion?->nivel,
                            'bin' => $stock->ubicacion?->casillero,
                            'quantity' => (int) $stock->cantidad,
                        ])->values()->all(),
                    ];
                })->values()->all(),
        ];
    }
}
