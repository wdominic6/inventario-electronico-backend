# Backend API – Sistema Web de Gestión Logística e Inventario de Productos Electrónicos

Repositorio `inventario-electronico-backend` del **Grupo 6 – Ingeniería de Software 2026-II (UNA Puno)**.
Provee la API REST centralizada para el control de catálogo técnico, inventario multi-sucursal, movimientos transaccionales, consultas en tiempo real y alertas de desabastecimiento.

## Stack Tecnológico Real
- **Framework**: Laravel 13 (PHP 8.4-FPM en contenedor Docker `app_backend`).
- **Base de datos**: MySQL 8.4 (motor InnoDB con soporte ACID y bloqueo por fila `FOR UPDATE`, en contenedor `app_db`).
- **Autenticación**: Laravel Sanctum (tokens Bearer por usuario con roles: `admin`, `almacen`, `ventas`).
- **Calidad y Pruebas**: PHPUnit 12 (`php artisan test`) y Laravel Pint (`./vendor/bin/pint`).

## Ejecución de Comandos (Siempre vía Docker)
No instales PHP ni Composer en el sistema operativo host. Ejecuta todos los comandos a través del entorno Docker ubicado en `../infra`:
```powershell
docker compose -f ../infra/compose.yaml exec backend php artisan <comando>
docker compose -f ../infra/compose.yaml exec backend composer <comando>
docker compose -f ../infra/compose.yaml exec backend php artisan test
docker compose -f ../infra/compose.yaml exec backend ./vendor/bin/pint
```

## Regla de Oro de Trabajo en Equipo (Aislamiento Estricto)
Este repositorio es desarrollado por **dos integrantes de Backend** con fronteras estrictas de archivos (detalladas en `.agents/rules/aislamiento-equipo-backend.md`):
1. **Tito Monteagudo William Dominic** (*Arquitectura API, Seguridad y Transacciones*):
   - Responsable de Autenticación/Usuarios/Auditoría, CRUD de Catálogo Técnico/Sucursales/Ubicaciones y Movimientos Transaccionales (Entradas, Salidas y Traslados con `lockForUpdate`).
   - Sus rutas van exclusivamente en `routes/api_transaccional.php`.
2. **Quispe Cruz Anthony Junior** (*Base de Datos, Consultas y QA*):
   - Responsable del esquema relacional (`database/migrations/`, `seeders/`, `factories/`, `app/Models/`), Consultas avanzadas de disponibilidad multi-sucursal, Reportes/Alertas de bajo stock y Pruebas técnicas (`tests/`).
   - Sus rutas van exclusivamente en `routes/api_consultas.php`.

> **IMPORTANTE PARA EL AGENTE**: Antes de crear o modificar cualquier archivo, identifica el integrante activo según la tarea solicitada. **Está prohibido modificar archivos asignados al otro integrante** o cambiar las firmas de los endpoints pactados en `.agents/rules/contrato-endpoints.md` sin autorización explícita.
