<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_sucursal,
            'code' => $this->codigo,
            'name' => $this->nombre_sucursal,
            'address' => $this->direccion,
            'phone' => $this->telefono,
            'is_active' => $this->estado === 'activa',
        ];
    }
}
