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
            'id_producto' => $this->id_producto,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'modelo' => $this->modelo,
            'encapsulado' => $this->encapsulado,
            'voltaje_operacion' => $this->voltaje_operacion,
            'tolerancia' => $this->tolerancia,
            'especificaciones_extra' => $this->especificaciones_extra,
            'categoria' => [
                'id_categoria' => $this->categoria?->id_categoria,
                'nombre_categoria' => $this->categoria?->nombre_categoria,
            ],
            'stock_total' => (int) $this->stocks->sum('cantidad'),
            'stock_por_sucursal' => $this->stocks
                ->groupBy('id_sucursal')
                ->map(function ($stocks): array {
                    $primero = $stocks->first();

                    return [
                        'id_sucursal' => $primero->id_sucursal,
                        'nombre_sucursal' => $primero->sucursal?->nombre_sucursal,
                        'cantidad' => (int) $stocks->sum('cantidad'),
                        'ubicaciones' => $stocks->map(fn ($stock): array => [
                            'id_ubicacion' => $stock->id_ubicacion,
                            'estante' => $stock->ubicacion?->estante,
                            'nivel' => $stock->ubicacion?->nivel,
                            'casillero' => $stock->ubicacion?->casillero,
                            'cantidad' => (int) $stock->cantidad,
                        ])->values()->all(),
                    ];
                })->values()->all(),
        ];
    }
}
