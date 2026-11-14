<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_ubicacion,
            'branch_id' => $this->id_sucursal,
            'branch' => new BranchResource($this->whenLoaded('sucursal')),
            'code' => $this->codigo,
            'shelf' => $this->estante,
            'level' => $this->nivel,
            'bin' => $this->casillero,
            'is_active' => $this->estado === 'disponible',
            'description' => $this->descripcion,
        ];
    }
}
