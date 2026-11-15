<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LowStockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentStock = (int) $this->stock_actual;
        $minimumStock = (int) $this->stock_minimo;

        return [
            'product_id' => (int) $this->id_producto,
            'sku' => $this->codigo,
            'name' => $this->nombre,
            'branch_id' => (int) $this->id_sucursal,
            'branch_name' => $this->nombre_sucursal,
            'current_stock' => $currentStock,
            'min_stock' => $minimumStock,
            'severity' => $currentStock === 0 ? 'critical' : 'warning',
        ];
    }
}
