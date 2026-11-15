<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $normalizedType = match ($this->nombre_tipo ?? null) {
            'Entrada Compra', 'Ajuste Positivo' => 'entry',
            'Salida Venta', 'Ajuste Negativo' => 'exit',
            'Traslado Saliente', 'Traslado Entrante' => 'transfer',
            default => $this->nombre_tipo,
        };

        return [
            'id' => (int) $this->id_movimiento_kardex,
            'type' => $normalizedType,
            'movement_type_name' => $this->nombre_tipo,
            'factor' => isset($this->factor) ? (int) $this->factor : null,
            'product_id' => (int) $this->id_producto,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'product' => [
                'id' => (int) $this->id_producto,
                'sku' => $this->codigo,
                'name' => $this->nombre,
            ],
            'branch_id' => (int) $this->id_sucursal,
            'branch' => [
                'id' => (int) $this->id_sucursal,
                'name' => $this->nombre_sucursal ?? null,
            ],
            'location_id' => (int) $this->id_ubicacion,
            'location' => [
                'id' => (int) $this->id_ubicacion,
                'shelf' => $this->estante ?? null,
                'level' => $this->nivel ?? null,
                'bin' => $this->casillero ?? null,
            ],
            'user_id' => (int) $this->id_usuario,
            'user' => [
                'id' => (int) $this->id_usuario,
                'name' => trim(($this->nombre_usuario ?? '').' '.($this->apellido_usuario ?? '')),
                'email' => $this->email_usuario ?? null,
            ],
            'quantity' => (int) $this->cantidad,
            'cantidad' => (int) $this->cantidad,
            'previous_stock' => (int) $this->stock_anterior,
            'new_stock' => (int) $this->stock_resultante,
            'stock_anterior' => (int) $this->stock_anterior,
            'stock_resultante' => (int) $this->stock_resultante,
            'entry_id' => $this->id_entrada !== null ? (int) $this->id_entrada : null,
            'exit_id' => $this->id_salida !== null ? (int) $this->id_salida : null,
            'transfer_id' => $this->id_transferencia !== null ? (int) $this->id_transferencia : null,
            'notes' => $this->observacion ?? null,
            'created_at' => $this->fecha_hora,
            'fecha_hora' => $this->fecha_hora,
        ];
    }
}
