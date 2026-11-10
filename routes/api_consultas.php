<?php

use App\Http\Controllers\Api\StockController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Consultas Multi-Sucursal, Reportes y Alertas (Exclusivo: Anthony Junior)
|--------------------------------------------------------------------------
| Módulos 4 y 5 del Contrato de Endpoints (.agents/rules/contrato-endpoints.md):
| - /api/stock, /api/stock/search
| - /api/alerts/low-stock
| - /api/reports/dashboard-summary, /api/reports/rotation, /api/reports/kardex/{product}
*/

Route::middleware('auth:sanctum')
    ->middleware(RoleMiddleware::class.':admin,ventas,almacen')
    ->group(function (): void {
        Route::get('/stock', [StockController::class, 'buscarStock']);
        Route::get('/stock/search', [StockController::class, 'buscarStock']);
    });
