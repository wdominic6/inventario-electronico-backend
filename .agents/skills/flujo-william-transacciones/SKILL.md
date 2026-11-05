---
name: flujo-william-transacciones
description: >-
  Guía de implementación para Tito Monteagudo William Dominic en el backend
  (Autenticación Sanctum, Usuarios/Auditoría, CRUD de Catálogo Técnico y
  Movimientos Transaccionales de Stock con lockForUpdate). Úsalo cuando se pida
  crear o modificar endpoints transaccionales, de seguridad o de catálogo en backend.
---

# Flujo de Desarrollo Backend – William Dominic (Arquitectura API y Transacciones)

Este skill guía la implementación de los módulos asignados a **Tito Monteagudo William Dominic** respetando las fronteras del equipo.

## Reglas Previas
1. Verifica que **solo** crearás o editarás rutas dentro de `routes/api_transaccional.php` (asegurando que `routes/api.php` lo requiera).
2. No modifiques `routes/api_consultas.php` ni los controladores de consultas/reportes de Anthony.
3. Revisa las firmas exactas y payloads en `.agents/rules/contrato-endpoints.md` (Módulos 1, 2 y 3).

## Pasos para Implementar un Endpoint Transaccional o de Catálogo

1. **Crear el FormRequest**:
   - En `app/Http/Requests/Auth/`, `Catalog/` o `Movement/`.
   - Define reglas estrictas y mensajes en español.

2. **Implementar la Lógica de Servicio (para Movimientos)**:
   - En `app/Services/StockTransactionService.php`, envuelve toda actualización de stock en `DB::transaction()`.
   - Ordena los ítems por `product_id` y `location_id` antes de aplicar `->lockForUpdate()` para prevenir *deadlocks* en MySQL 8.4 cuando dos sucursales transfieren varios productos al mismo tiempo.
   - Si el stock es insuficiente en una salida o traslado, lanza una excepción que retorne HTTP `409 Conflict` con `{ "message": "Stock insuficiente para el componente..." }`.
   - Registra el `previous_stock` y `new_stock` en el detalle del movimiento y registra el evento en `AuditService`.

3. **Crear el Controlador y el JsonResource**:
   - Retorna siempre `new Resource($model)` o `Resource::collection($paginator)` con código HTTP `200`, `201` o `204` según el contrato.

4. **Registrar la Ruta en `routes/api_transaccional.php`**:
   - Aplica el middleware `auth:sanctum` y el filtro de rol correspondiente (`admin`, `almacen`).

5. **Formatear y Verificar**:
   ```powershell
   docker compose -f ../infra/compose.yaml exec backend ./vendor/bin/pint
   docker compose -f ../infra/compose.yaml exec backend php artisan route:list --path=api
   ```
