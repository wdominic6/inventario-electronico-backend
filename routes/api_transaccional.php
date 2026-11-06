<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Transaccionales, Seguridad y Catálogo (Exclusivo: William Dominic)
|--------------------------------------------------------------------------
| Módulos 1, 2 y 3 del Contrato de Endpoints (.agents/rules/contrato-endpoints.md):
| - /api/auth/*, /api/users, /api/audit-logs
| - /api/branches, /api/categories, /api/locations, /api/products
| - /api/movements, /api/movements/entry, /api/movements/exit, /api/movements/transfer
*/

// Módulo 1: Autenticación pública
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas protegidas por token Bearer de Laravel Sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    // Módulo 1: Sesión actual
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Módulo 2: Consultas de Catálogo abiertas a todos los roles autenticados (admin, almacen, ventas)
    Route::middleware(RoleMiddleware::class.':admin,almacen,ventas')->group(function (): void {
        Route::get('/branches', [BranchController::class, 'index']);
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/locations', [LocationController::class, 'index']);
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product');
    });

    // Módulo 1 y 2: Administración exclusiva (rol: admin)
    Route::middleware(RoleMiddleware::class.':admin')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update'])->whereNumber('user');

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->whereNumber('branch');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->whereNumber('branch');
    });

    // Módulo 2 y 3: Gestión de Almacén y Movimientos Transaccionales (roles: admin, almacen)
    Route::middleware(RoleMiddleware::class.':admin,almacen')->group(function (): void {
        // CRUD de Categorías
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->whereNumber('category');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category');

        // CRUD de Ubicaciones en Estantería
        Route::post('/locations', [LocationController::class, 'store']);
        Route::put('/locations/{location}', [LocationController::class, 'update'])->whereNumber('location');
        Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->whereNumber('location');

        // CRUD de Componentes Electrónicos (Productos)
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update'])->whereNumber('product');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->whereNumber('product');

        // Movimientos Logísticos ACID (Entradas, Salidas y Traslados con lockForUpdate)
        Route::get('/movements', [MovementController::class, 'index']);
        Route::post('/movements/entry', [MovementController::class, 'storeEntry']);
        Route::post('/movements/exit', [MovementController::class, 'storeExit']);
        Route::post('/movements/transfer', [MovementController::class, 'storeTransfer']);
        Route::get('/movements/{movement}', [MovementController::class, 'show'])->whereNumber('movement');
    });
});
