<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Registra un evento de auditoría en la tabla logs_auditoria.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $details = null,
        ?int $userId = null,
        ?Request $request = null
    ): ?AuditLog {
        $request ??= request();
        $resolvedUserId = $userId ?? Auth::id();

        if ($resolvedUserId === null) {
            return null;
        }

        return AuditLog::query()->create([
            'id_usuario' => $resolvedUserId,
            'accion' => $action,
            'tabla_afectada' => $entityType ?? 'sistema',
            'registro_id' => $entityId,
            'valores_nuevos' => array_filter([
                'descripcion' => $description,
                'detalles' => $details,
            ], static fn ($value): bool => $value !== null),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
