<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_usuario,
            'name' => trim($this->nombre.' '.$this->apellido),
            'email' => $this->email,
            'role' => match ($this->nombreRol()) {
                'Administrador' => 'admin',
                'Almacén' => 'almacen',
                'Ventas' => 'ventas',
                default => $this->nombreRol(),
            },
            'is_active' => $this->estado === 'activo',
            'branch_id' => $this->id_sucursal,
            'branch_name' => $this->sucursal?->nombre_sucursal,
            'branch' => new BranchResource($this->whenLoaded('sucursal')),
            'last_access' => $this->ultimo_acceso?->toISOString(),
        ];
    }
}
