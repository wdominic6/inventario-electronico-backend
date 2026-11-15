# Colección Postman de la API

## Requisitos

Desde la carpeta `infra`, levantar Docker y preparar los datos semilla:

```powershell
docker compose up -d
docker compose exec backend php artisan migrate:fresh --seed --env=testing
```

En Postman importar:

1. `Inventario-API.postman_collection.json`
2. `Inventario-Local.postman_environment.json`

Seleccionar el ambiente **Inventario - Local Docker** y ejecutar la colección
completa con Collection Runner.

## Orden de ejecución

La colección está organizada para ejecutarse en este orden:

1. Health check.
2. Login de administrador, ventas y almacén.
3. Catálogo.
4. Sucursales y ubicaciones.
5. Stock y movimientos.
6. Alertas y reportes.
7. Usuarios y auditoría.
8. Casos negativos.

Los scripts guardan automáticamente los tokens e identificadores semilla en las
variables del ambiente. Los registros creados por la colección usan valores
basados en `{{$timestamp}}` para evitar conflictos de unicidad.

## Cobertura

La colección incluye:

- Autenticación y sesión.
- Catálogo de categorías y productos.
- Sucursales y ubicaciones.
- Consulta multicriterio de stock.
- Entradas, salidas y transferencias.
- Alertas, dashboard, rotación y Kardex.
- Usuarios y auditoría.
- Casos `401`, `403`, `409` y `422`.

El tiempo máximo de respuesta se valida en el evento de colección con un umbral
de 2 segundos.
