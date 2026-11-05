---
name: flujo-anthony-bd-consultas-qa
description: >-
  Guía de implementación para Quispe Cruz Anthony Junior en el backend
  (Migraciones MySQL 8.4, Modelos Eloquent, Seeders, Consultas Multi-Sucursal
  en tiempo real, Reportes/Alertas de bajo stock y Pruebas PHPUnit de endpoints
  y concurrencia). Úsalo cuando se trabaje en base de datos, consultas, reportes o QA.
---

# Flujo de Desarrollo Backend – Anthony Junior (Base de Datos, Consultas y QA)

Este skill guía la implementación de los módulos asignados a **Quispe Cruz Anthony Junior** respetando las fronteras del equipo.

## Reglas Previas
1. Verifica que **solo** crearás o editarás rutas dentro de `routes/api_consultas.php` (asegurando que `routes/api.php` lo requiera).
2. No modifiques `routes/api_transaccional.php` ni `StockTransactionService.php` ni los controladores de William.
3. Revisa las firmas exactas y payloads en `.agents/rules/contrato-endpoints.md` (Módulos 4 y 5).

## Pasos según la Tarea

### A. Diseño de Base de Datos, Migraciones y Seeders
1. Diseña las tablas en `database/migrations/` asegurando integridad referencial (`foreignId()->constrained()`) e índices compuestos en `product_stocks(branch_id, product_id, location_id)` y `inventory_movements(branch_id, created_at, type)`.
2. Define los modelos en `app/Models/` con relaciones Eloquent tipadas y scopes reutilizables (ej. `scopeLowStock`, `scopeFilterBySpecs`).
3. Crea Seeders realistas en `database/seeders/` con sucursales (ej. Sucursal Central Puno, Sucursal Juliaca), estanterías y componentes electrónicos reales (Arduino, ESP32, resistencias SMD/THT, sensores ultrasónicos, multímetros).

### B. Endpoints de Consultas Multi-Sucursal y Reportes (`routes/api_consultas.php`)
1. Implementa `StockQueryController` (`GET /api/stock/search`, `GET /api/stock/availability/{product}`, `GET /api/stock/by-branch/{branch}`) usando `with(['category', 'stocks.branch', 'stocks.location'])` para evitar problemas N+1.
2. Implementa `AlertController` (`GET /api/alerts/low-stock`) y `ReportController` (`dashboard-summary`, `rotation`, `kardex/{product}`).
3. Devuelve siempre los datos mediante `JsonResource` cumpliendo el contrato JSON para que **Franklin** los consuma en el frontend sin fricción.

### C. Ejecución de Pruebas Técnicas y de Concurrencia (QA)
1. Escribe pruebas en `tests/Feature/` cubriendo:
   - Exactitud de stock tras entradas, salidas y traslados.
   - Rechazo con HTTP `409 Conflict` cuando una salida supera el stock de la sucursal.
   - Filtros técnicos de búsqueda y alertas de `min_stock`.
2. Ejecuta la suite de pruebas vía Docker:
   ```powershell
   docker compose -f ../infra/compose.yaml exec backend php artisan test
   docker compose -f ../infra/compose.yaml exec backend ./vendor/bin/pint
   ```
