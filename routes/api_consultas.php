<?php

use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ReportController;
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

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::class.':admin,ventas,almacen',
])
    ->group(function (): void {
        Route::get('/stock', [StockController::class, 'buscarStock']);
        Route::get('/stock/search', [StockController::class, 'buscarStock']);

        Route::get('/alerts/low-stock', [AlertController::class, 'lowStock']);
    });

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::class.':admin',
])->group(function (): void {
    Route::get('/reports/dashboard-summary', [ReportController::class, 'dashboardSummary']);
    Route::get('/reports/rotation', [ReportController::class, 'rotation']);
});

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::class.':admin,almacen',
])->group(function (): void {
    Route::get('/reports/kardex/{product}', [ReportController::class, 'kardex'])
        ->whereNumber('product');
});
