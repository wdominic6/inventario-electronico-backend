<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_log,
            'user_id' => $this->id_usuario,
            'user' => new UserResource($this->whenLoaded('usuario')),
            'action' => $this->accion,
            'entity_type' => $this->tabla_afectada,
            'entity_id' => $this->registro_id,
            'details' => $this->valores_nuevos,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->fecha_hora?->toISOString(),
        ];
    }
}
