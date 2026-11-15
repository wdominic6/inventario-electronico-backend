<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => (int) $this->id_producto,
            'sku' => $this->codigo,
            'name' => $this->nombre,
            'category' => $this->nombre_categoria,
            'movement_count' => (int) $this->movement_count,
            'quantity_moved' => (int) $this->quantity_moved,
        ];
    }
}
