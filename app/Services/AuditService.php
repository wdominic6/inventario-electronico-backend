<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Registra un evento de auditoría en la tabla logs_auditoria.
     *
     * @param  array<string, mixed>|null  $details
     * @param  array<string, mixed>|null  $oldValues
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $details = null,
        ?int $userId = null,
        ?Request $request = null,
        ?array $oldValues = null
    ): ?AuditLog {
        $request ??= request();
        $resolvedUserId = $userId ?? Auth::id();

        if ($resolvedUserId === null) {
            return null;
        }

        $userAgent = $request?->userAgent();

        return AuditLog::query()->create([
            'id_usuario' => $resolvedUserId,
            'accion' => Str::substr($action, 0, 30),
            'tabla_afectada' => Str::substr($entityType ?? 'sistema', 0, 100),
            'registro_id' => $entityId,
            'valores_anteriores' => $oldValues,
            'valores_nuevos' => array_filter([
                'descripcion' => $description,
                'detalles' => $details,
            ], static fn ($value): bool => $value !== null),
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent !== null ? Str::substr($userAgent, 0, 255) : null,
            'fecha_hora' => now(),
        ]);
    }
}
