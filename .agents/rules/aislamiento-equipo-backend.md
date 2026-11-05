# Aislamiento Estricto de Equipo en Backend (William vs. Anthony)

Para evitar conflictos de `git merge` cuando ambos desarrolladores de Backend trabajen en paralelo asistidos por IA, se aplica la siguiente partición obligatoria de archivos y responsabilidades basada en el Plan de Proyecto (Secciones 10, 11, 14 y 15).

## 1. Partición del Archivo de Rutas (`routes/api.php`)
El archivo `routes/api.php` actúa **únicamente como cargador** y no debe contener lógica de negocio directa. Debe incluir:
```php
require __DIR__.'/api_transaccional.php'; // Exclusivo de William Dominic
require __DIR__.'/api_consultas.php';     // Exclusivo de Anthony Junior
```
Ningún integrante debe agregar rutas en el archivo del otro.

## 2. Matriz de Propiedad de Archivos en `backend/`

### A. Dominio de Tito Monteagudo William Dominic
*(Rol: Arquitectura de la API, Seguridad y Transacciones – Semanas 9, 11, 15)*

**Archivos permitidos para crear/modificar:**
- `routes/api_transaccional.php`
- `app/Http/Middleware/*` (verificación de roles `admin`, `almacen`, `ventas`)
- `app/Http/Controllers/Api/AuthController.php`
- `app/Http/Controllers/Api/UserController.php`
- `app/Http/Controllers/Api/AuditLogController.php`
- `app/Http/Controllers/Api/BranchController.php`
- `app/Http/Controllers/Api/CategoryController.php`
- `app/Http/Controllers/Api/LocationController.php`
- `app/Http/Controllers/Api/ProductController.php`
- `app/Http/Controllers/Api/MovementController.php`
- `app/Http/Requests/Auth/*`, `app/Http/Requests/Catalog/*`, `app/Http/Requests/Movement/*`
- `app/Http/Resources/UserResource.php`, `BranchResource.php`, `CategoryResource.php`, `LocationResource.php`, `ProductResource.php`, `MovementResource.php`, `AuditLogResource.php`
- `app/Services/StockTransactionService.php` y `app/Services/AuditService.php`

**Archivos prohibidos cuando el agente asiste a William:**
- `routes/api_consultas.php`
- Controladores, FormRequests y Resources de Consultas y Reportes (`StockQueryController`, `AlertController`, `ReportController`).
- Archivos de pruebas de QA en `tests/` asignados a Anthony (salvo solicitud explícita de coordinación).

---

### B. Dominio de Quispe Cruz Anthony Junior
*(Rol: Base de Datos, Consultas y QA – Semanas 4, 5, 7, 11, 12, 14)*

**Archivos permitidos para crear/modificar:**
- `database/migrations/*` (Esquema DER normalizado en MySQL 8.4 InnoDB)
- `database/factories/*` y `database/seeders/*` (Datos de prueba realistas de componentes electrónicos)
- `app/Models/*` (`User`, `Branch`, `Category`, `Location`, `Product`, `ProductStock`, `InventoryMovement`, `AuditLog`)
- `routes/api_consultas.php`
- `app/Http/Controllers/Api/StockQueryController.php`
- `app/Http/Controllers/Api/AlertController.php`
- `app/Http/Controllers/Api/ReportController.php`
- `app/Http/Requests/Query/*`, `app/Http/Requests/Report/*`
- `app/Http/Resources/StockAvailabilityResource.php`, `AlertResource.php`, `ReportResource.php`
- `tests/Feature/*` y `tests/Unit/*` (Pruebas técnicas de endpoints y pruebas de concurrencia de stock)

**Archivos prohibidos cuando el agente asiste a Anthony:**
- `routes/api_transaccional.php`
- Controladores de Auth, Usuarios, Catálogo CRUD y Movimientos (`AuthController`, `ProductController`, `MovementController`, etc.).
- `app/Services/StockTransactionService.php`.

## 3. Protocolo sobre Modelos y Migraciones Compartidas
Los modelos en `app/Models/*` y las migraciones en `database/migrations/*` son administrados por **Anthony** como responsable de Base de Datos. Si **William** necesita un método auxiliar o relación nueva en un Modelo para sus transacciones, el agente debe advertirlo claramente para coordinar el cambio sin romper las consultas ni los seeders de Anthony.
