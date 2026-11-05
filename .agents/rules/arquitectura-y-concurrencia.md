# Reglas de Arquitectura, Base de Datos y Concurrencia (`backend`)

## 1. Control de Concurrencia en Movimientos de Stock (Obligatorio)
El sistema opera con múltiples sucursales registrando entradas, salidas y traslados simultáneamente sobre **MySQL 8.4 (InnoDB)**.
- **Toda operación que modifique existencias** (`entry`, `exit`, `transfer`) debe ejecutarse dentro de `DB::transaction(function () { ... })`.
- Antes de descontar o actualizar la fila en `product_stocks`, **es obligatorio bloquear la fila con bloqueo pesimista (`->lockForUpdate()`)**:
  ```php
  $stock = ProductStock::where('branch_id', $branchId)
      ->where('product_id', $productId)
      ->where('location_id', $locationId)
      ->lockForUpdate()
      ->first();
  ```
- Si en una salida o traslado `$stock` no existe o `$stock->quantity < $requestedQuantity`, se debe abortar la transacción lanzando una respuesta HTTP `409 Conflict` con un mensaje claro indicando el stock actual disponible.
- Cada movimiento debe guardar en la tabla de detalle el `previous_stock` y el `new_stock` para garantizar trazabilidad de Kardex y auditoría inmutable.

## 2. Especificaciones Técnicas de Componentes Electrónicos
Los productos electrónicos (resistencias, microcontroladores, sensores, placas, equipos de medición) requieren parámetros técnicos específicos:
- La columna `specs` en `products` debe ser de tipo `JSON` en MySQL 8.4 (casteada como `'array'` en el modelo Eloquent `Product`) o estar acompañada de campos indexados (`sku`, `barcode`, `brand`, `model`) para permitir búsquedas rápidas por `voltage`, `tolerance`, `package_type` (ej. `SMD 0805`, `DIP-8`, `TO-220`), `resistance`, `current`, etc.

## 3. Validación, Recursos y Seguridad
- **FormRequests obligatorios**: Nunca valides con `$request->validate()` directo en el controlador. Crea un `FormRequest` en `app/Http/Requests/` con mensajes en español.
- **Eloquent API Resources**: Toda respuesta de controlador debe usar `JsonResource` o `ResourceCollection` en `app/Http/Resources/` para asegurar el contrato `{ "data": ... }`.
- **Roles con Sanctum**: Verifica siempre el rol del usuario autenticado (`admin`, `almacen`, `ventas`) según la tabla de `.agents/rules/contrato-endpoints.md`.
- **Estilo de código**: Ejecuta Laravel Pint (`./vendor/bin/pint`) al finalizar cambios en archivos PHP.
