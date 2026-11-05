# Contrato Estricto de Endpoints (Puente Backend ↔ Frontend)

Este documento define el contrato inmutable de comunicación entre `backend` (Laravel 13) y `frontend` (Vue 3). Ningún agente puede cambiar URLs, verbos HTTP, nombres de campos JSON ni códigos de estado sin actualizar ambos lados del contrato.

## 1. Formato Estándar de Petición y Respuesta
- **Prefijo base**: Todas las rutas llevan el prefijo `/api` (en Laravel `routes/api.php` aplica `/api` automáticamente).
- **Cabeceras obligatorias**: `Accept: application/json`, `Content-Type: application/json` y `Authorization: Bearer <token>` (excepto en `/api/auth/login`).
- **Respuesta exitosa de entidad o colección (`200 OK` / `201 Created`)**:
  ```json
  {
    "data": { ... },
    "meta": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 15,
      "total": 72
    }
  }
  ```
- **Respuesta de error (`401`, `403`, `404`, `409 Conflict`, `422 Unprocessable Entity`)**:
  ```json
  {
    "message": "Mensaje descriptivo en español para mostrar al usuario.",
    "errors": {
      "campo": ["Detalle de validación en español."]
    }
  }
  ```
  > Nota: El código `409 Conflict` se utiliza cuando una salida o traslado solicita más cantidad que el stock disponible en la sucursal/ubicación seleccionada.

---

## 2. Tabla Maestra de Endpoints por Módulo y Responsable

### Módulo 1: Seguridad, Usuarios y Auditoría
*Archivo Backend*: `routes/api_transaccional.php` (**William**) | *Servicio Frontend*: `src/services/auth.service.js` (**Carlos**)

| Método | Ruta | Roles Permitidos | Descripción y Payload Clave |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/auth/login` | Público | Body: `{ email, password }`. Retorna `{ data: { token, user: { id, name, email, role, branch_id, branch_name } } }`. |
| `POST` | `/api/auth/logout` | Autenticado | Revoca el token actual de Sanctum. Retorna `204 No Content`. |
| `GET` | `/api/auth/me` | Autenticado | Retorna `{ data: { id, name, email, role, branch_id, branch } }`. |
| `GET` | `/api/users` | `admin` | Lista paginada de usuarios (`?role=`, `?branch_id=`, `?q=`). |
| `POST` | `/api/users` | `admin` | Crea usuario con `{ name, email, password, role: 'admin'|'almacen'|'ventas', branch_id, is_active }`. |
| `PUT` | `/api/users/{user}` | `admin` | Actualiza datos, rol, sucursal asignada o estado (`is_active`) del usuario. |
| `GET` | `/api/audit-logs` | `admin` | Historial paginado de auditoría (`?user_id=`, `?action=`, `?date_from=`, `?date_to=`). |

---

### Módulo 2: Catálogo Técnico, Sucursales y Ubicaciones
*Archivo Backend*: `routes/api_transaccional.php` (**William**) | *Servicio Frontend*: `src/services/catalogo.service.js` (**Carlos**)

| Método | Ruta | Roles Permitidos | Descripción y Payload Clave |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/branches` | Todos | Lista de sucursales/almacenes (`id`, `code`, `name`, `address`, `phone`, `is_active`). |
| `POST`, `PUT`, `DELETE` | `/api/branches/{branch?}` | `admin` | CRUD de sucursales. |
| `GET` | `/api/categories` | Todos | Lista de categorías electrónicas (Resistencias, Microcontroladores, Sensores, Placas, Herramientas, etc.). |
| `POST`, `PUT`, `DELETE` | `/api/categories/{category?}` | `admin`, `almacen` | CRUD de categorías (`name`, `code_prefix`, `description`). |
| `GET` | `/api/locations` | Todos | Lista de ubicaciones físicas en estantería filtrable por `?branch_id=` (`id`, `branch_id`, `code`, `shelf`, `level`, `bin`, `description`). |
| `POST`, `PUT`, `DELETE` | `/api/locations/{location?}` | `admin`, `almacen` | CRUD de ubicaciones físicas dentro de cada sucursal. |
| `GET` | `/api/products` | Todos | Catálogo paginado (`?q=`, `?category_id=`). Incluye especificaciones técnicas (`specs`). |
| `GET` | `/api/products/{product}` | Todos | Detalle de componente electrónico incluyendo `specs` y resumen de stock por sucursal. |
| `POST`, `PUT`, `DELETE` | `/api/products/{product?}` | `admin`, `almacen` | Payload: `{ sku, barcode, name, category_id, brand, model, unit, min_stock, unit_cost, unit_price, specs: { voltage, current, resistance, tolerance, package_type, pins, extra }, is_active }`. |

---

### Módulo 3: Movimientos Logísticos (Transacciones ACID)
*Archivo Backend*: `routes/api_transaccional.php` (**William**) | *Servicio Frontend*: `src/services/movimientos.service.js` (**Carlos**)

| Método | Ruta | Roles Permitidos | Descripción y Payload Clave |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/movements` | `admin`, `almacen` | Historial paginado de movimientos (`?branch_id=`, `?product_id=`, `?type=entry|exit|transfer`, `?date_from=`, `?date_to=`). |
| `GET` | `/api/movements/{movement}` | `admin`, `almacen` | Detalle completo de una transacción con su responsable (`user`), sucursal, ubicación y líneas de productos. |
| `POST` | `/api/movements/entry` | `admin`, `almacen` | **Entrada de mercancía** (recepción de proveedor o ajuste positivo). Body: `{ branch_id, reference_code, supplier_name, notes, items: [{ product_id, location_id, quantity, unit_cost }] }`. |
| `POST` | `/api/movements/exit` | `admin`, `almacen` | **Salida de mercancía** (despacho por venta o baja). Body: `{ branch_id, reference_code, reason: 'sale'|'damaged'|'internal_use', notes, items: [{ product_id, location_id, quantity }] }`. Valida stock suficiente con `lockForUpdate()`. |
| `POST` | `/api/movements/transfer` | `admin`, `almacen` | **Traslado entre sucursales**. Body: `{ source_branch_id, target_branch_id, reference_code, notes, items: [{ product_id, source_location_id, target_location_id, quantity }] }`. Descuenta en origen e incrementa en destino dentro de una sola transacción ACID. |

---

### Módulo 4: Consultas en Tiempo Real y Disponibilidad Multi-Sucursal (Ventas)
*Archivo Backend*: `routes/api_consultas.php` (**Anthony**) | *Servicio Frontend*: `src/services/consultas.service.js` (**Franklin**)

| Método | Ruta | Roles Permitidos | Descripción y Payload Clave |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/stock/search` | `admin`, `ventas`, `almacen` | Buscador rápido multi-filtro: `?q=` (SKU, código de barras, nombre, modelo), `?category_id=`, `?branch_id=`, `?package_type=`, `?voltage=`, `?only_available=true`. Retorna cada producto con su stock total y desglose por sucursal. |
| `GET` | `/api/stock/availability/{product}` | `admin`, `ventas`, `almacen` | Matriz de disponibilidad cruzada en tiempo real de un componente en **todas las sucursales**, indicando en qué estante/nivel/casillero (`shelf`, `level`, `bin`) se encuentra y su estado (`ok`, `low_stock`, `out_of_stock`). |
| `GET` | `/api/stock/by-branch/{branch}` | `admin`, `ventas`, `almacen` | Inventario detallado de una sucursal específica con filtros técnicos y de ubicación física. |

---

### Módulo 5: Reportes Estratégicos y Alertas de Desabastecimiento
*Archivo Backend*: `routes/api_consultas.php` (**Anthony**) | *Servicio Frontend*: `src/services/reportes.service.js` (**Franklin**)

| Método | Ruta | Roles Permitidos | Descripción y Payload Clave |
| :--- | :--- | :--- | :--- |
| `GET` | `/api/alerts/low-stock` | `admin`, `almacen`, `ventas` | Lista de componentes cuyo stock actual es menor o igual a `min_stock` (stock de seguridad), filtrable por `?branch_id=` y `?severity=critical|warning`. |
| `GET` | `/api/reports/dashboard-summary` | `admin` | KPIs para el panel de control gerencial: total de SKU activos, valorización total del inventario, conteo de alertas de quiebre de stock, movimientos del día/semana y sucursales operativas. |
| `GET` | `/api/reports/rotation` | `admin` | Métricas de rotación de inventario (clasificación ABC / componentes de mayor y menor movimiento) en un rango `?date_from=&date_to=&branch_id=`. |
| `GET` | `/api/reports/kardex/{product}` | `admin`, `almacen` | Historial cronológico (Kardex) de entradas, salidas, traslados y saldo resultante de un producto (`?branch_id=`, `?date_from=`, `?date_to=`). |
