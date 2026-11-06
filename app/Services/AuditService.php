<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuditService
{
    /**
     * Registra un evento de auditoría en la tabla audit_logs.
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
        if (! class_exists(AuditLog::class)) {
            return null;
        }

        $request ??= request();
        $resolvedUserId = $userId ?? Auth::id();

        try {
            return AuditLog::query()->create([
                'user_id' => $resolvedUserId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'details' => $details,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable) {
            // Evita interrumpir el flujo si la tabla audit_logs aún no ha sido migrada en entorno de pruebas.
            return null;
        }
    }
}
