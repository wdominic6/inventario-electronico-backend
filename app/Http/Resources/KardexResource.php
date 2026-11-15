<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KardexResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id_movimiento_kardex,
            'type' => $this->nombre_tipo,
            'factor' => (int) $this->factor,
            'quantity' => (int) $this->cantidad,
            'previous_stock' => (int) $this->stock_anterior,
            'resulting_stock' => (int) $this->stock_resultante,
            'branch' => [
                'id' => (int) $this->id_sucursal,
                'name' => $this->nombre_sucursal,
            ],
            'location' => [
                'id' => (int) $this->id_ubicacion,
                'shelf' => $this->estante,
                'level' => $this->nivel,
                'bin' => $this->casillero,
            ],
            'user' => [
                'id' => (int) $this->id_usuario,
                'name' => trim($this->nombre_usuario.' '.$this->apellido_usuario),
            ],
            'notes' => $this->observacion,
            'created_at' => $this->fecha_hora,
        ];
    }
}
