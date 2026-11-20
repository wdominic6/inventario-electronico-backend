<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    /**
     * GET /api/audit-logs
     */
    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->with(['usuario.rol', 'usuario.sucursal']);

        if ($request->filled('user_id')) {
            $query->where('id_usuario', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $action = $request->string('action')->toString();
            $query->where('accion', 'like', "%{$action}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('fecha_hora', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('fecha_hora', '<=', $request->string('date_to')->toString());
        }

        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return AuditLogResource::collection(
            $query->latest('id_log')->paginate($perPage)->withQueryString()
        );
    }
}
