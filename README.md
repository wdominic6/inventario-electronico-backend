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

## Datos de demostración

Para dejar una base limpia con el catálogo de componentes electrónicos, las tres
sucursales, sus ubicaciones, el inventario inicial y un usuario por rol:

```powershell
docker compose exec backend php artisan migrate:fresh --seed
```

El comando elimina y recrea todas las tablas. Las credenciales sembradas para la
demo son:

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | `admin@inventario.test` | `Admin#2026` |
| Ventas | `ventas@inventario.test` | `Ventas#2026` |
| Almacén | `almacen@inventario.test` | `Almacen#2026` |

## Base de datos de pruebas

Las pruebas PHPUnit utilizan una base MySQL independiente llamada
`proyecto_db_testing`. El archivo `backend/.env.testing` debe configurar la
conexión con el servicio Docker:

```env
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=proyecto_db_testing
DB_USERNAME=equipo_user
DB_PASSWORD=equipo_password
```

Crear la base de pruebas desde la carpeta `infra` (el comando solicitará la
contraseña del usuario root):

```powershell
docker compose exec db mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS proyecto_db_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON proyecto_db_testing.* TO 'equipo_user'@'%'; FLUSH PRIVILEGES;"
```

Ejecutar las migraciones y seeders únicamente sobre la base de pruebas:

```powershell
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan migrate:fresh --seed --env=testing
docker compose exec backend php artisan test
```

Para verificar la conexión activa:

```powershell
docker compose exec backend php artisan tinker --env=testing --execute="dump(config('app.env')); dump(config('database.connections.mysql.database')); dump(config('database.connections.mysql.host'));"
```

La salida esperada incluye `testing`, `proyecto_db_testing` y `db`.
No ejecutar `migrate:fresh` sin `--env=testing`, ya que podría eliminar la base
de desarrollo `proyecto_db`.

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
