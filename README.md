# Backend – Sistema Web de Gestión Logística e Inventario de Productos Electrónicos

API REST del proyecto del **Grupo 6 – Ingeniería de Software 2026-II (UNA Puno)**.

**Stack:** PHP 8.4 · Laravel 13 · Laravel Sanctum (autenticación por token) · MySQL 8.4

Repos relacionados: `inventario-electronico-frontend` (Vue), `inventario-electronico-infra` (Docker) y `inventario-electronico-docs` (documentación).

## Cómo ejecutarlo (con Docker)
El entorno se levanta desde el repo **infra**. Resumen:
```powershell
# en la carpeta del repo infra
docker compose up -d --build
docker compose exec backend composer install
docker compose exec backend cp .env.example .env     # solo la primera vez
docker compose exec backend php artisan key:generate # solo la primera vez
docker compose exec backend php artisan migrate
```
- API: http://localhost:8000/api
- Health check: http://localhost:8000/up

> Los valores `DB_*` de `.env` deben coincidir con los de `infra/.env` (`DB_HOST=db`).

## Comandos útiles
```powershell
docker compose exec backend php artisan test            # pruebas (PHPUnit)
docker compose exec backend php artisan route:list      # rutas de la API
docker compose exec backend ./vendor/bin/pint           # formato de código
```

## Módulos previstos
Seguridad y roles (Administrador, Ventas, Almacén) · Catálogo de componentes · Movimientos logísticos (entradas y salidas) · Consultas de stock entre sucursales · Reportes y alertas de stock mínimo · Auditoría.

## Responsables (según el plan)
- **Tito Monteagudo William Dominic**: arquitectura de la API y transacciones
- **Quispe Cruz Anthony Junior**: base de datos, consultas y QA

## Flujo de trabajo
- `main`: versión estable (solo se modifica con Pull Request).
- `develop`: integración.
- `feature/<issue>-<descripcion>`: una rama por issue. Abrir el PR hacia `develop` y enlazar el issue (`Closes #N`).
