<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\OrdenCompraController;
use App\Http\Controllers\Api\ClienteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/roles', function () {
        return Role::select('id', 'name')->get();
    });

    //USUARIOS
    Route::post('/users', [AuthController::class, 'register'])->middleware('role:superadmin|admin');

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('role:superadmin|admin');

    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('role:superadmin');

    Route::patch('/users/{id}/desactivar', [UserController::class, 'desactivar'])->middleware('role:superadmin');

    Route::patch('/users/{id}/activar', [UserController::class, 'activar'])->middleware('role:superadmin');

    //PLATAFORMAS Y PLANTILLAS
    Route::get('/plantillas', [CotizacionController::class, 'indexPlantillas'])
        ->middleware('role:superadmin|ventas');

    Route::get('/plataformas', [CotizacionController::class, 'indexPlataformas'])
        ->middleware('role:superadmin|ventas');
});

Route::prefix('productos')->middleware('auth:sanctum')->group(function () {
    //PRODUCTOS
    Route::get('/', [ProductoController::class, 'index']);
    Route::get('/{id}', [ProductoController::class, 'show']);

    Route::post('/', [ProductoController::class, 'store'])->middleware('role:superadmin|ventas|admin');

    Route::put('/{id}', [ProductoController::class, 'update'])->middleware('role:superadmin|ventas|admin');

    Route::delete('/{id}', [ProductoController::class, 'destroy'])->middleware('role:superadmin|ventas|admin');
});

Route::prefix('cotizaciones')->middleware('auth:sanctum')->group(function () {
    // ── RUTAS ESTÁTICAS PRIMERO ──────────────────────────────
    Route::get('/', [CotizacionController::class, 'index'])
        ->middleware('role:superadmin|ventas');

    Route::post('/', [CotizacionController::class, 'store'])
        ->middleware('role:superadmin|ventas');

    Route::post('/completa', [CotizacionController::class, 'storeCompleta'])->middleware('role:superadmin|ventas');

    Route::get('/items', [CotizacionController::class, 'indexItems'])
        ->middleware('role:superadmin|ventas');

    Route::get('/estados', [CotizacionController::class, 'indexEstadoCotizacion'])
        ->middleware('role:superadmin|ventas');

    Route::get('/monedas', [CotizacionController::class, 'indexMonedas'])
        ->middleware('role:superadmin|ventas');

    // ── RUTAS DINÁMICAS DESPUÉS ──────────────────────────────
    Route::get('/{id}', [CotizacionController::class, 'show'])
        ->middleware('role:superadmin|ventas');

    Route::put('/{id}', [CotizacionController::class, 'update'])
        ->middleware('role:superadmin|ventas');
    
    Route::put('/{id}/completa', [CotizacionController::class, 'updateCompleta'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{id}/recalcular', [CotizacionController::class, 'recalcular'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{id}/historial', [CotizacionController::class, 'historial'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/exportar-pdf', [CotizacionController::class, 'exportarPdf'])
        ->middleware('role:superadmin|ventas');

    // Items
    Route::post('/{id}/items', [CotizacionController::class, 'addItem'])
        ->middleware('role:superadmin|ventas');

    Route::put('/items/{id}', [CotizacionController::class, 'updateItem'])
        ->middleware('role:superadmin|ventas');

    Route::delete('/items/{id}', [CotizacionController::class, 'deleteItem'])
        ->middleware('role:superadmin|ventas');

    // Costos
    Route::post('/{id}/costos', [CotizacionController::class, 'addCosto'])
        ->middleware('role:superadmin|ventas');

    Route::delete('/costos/{id}', [CotizacionController::class, 'deleteCosto'])
        ->middleware('role:superadmin|ventas');
});

Route::prefix('ordencompra')->middleware('auth:sanctum')->group(function () {

    Route::get('/', [OrdenCompraController::class, 'index'])->middleware('role:superadmin|ventas');

    Route::post('/', [OrdenCompraController::class, 'store'])->middleware('role:superadmin|ventas');

    Route::get('{id}', [OrdenCompraController::class, 'show'])->middleware('role:superadmin|ventas');

    Route::patch(
        '{id}/estado',
        [OrdenCompraController::class, 'updateEstado']
    );

    Route::patch(
        'items/{id}/estado',
        [OrdenCompraController::class, 'updateEstadoItem']
    );
});

Route::prefix('clientes')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ClienteController::class, 'index'])->middleware('role:superadmin|ventas');
    Route::post('/', [ClienteController::class, 'store'])->middleware('role:superadmin|ventas');
    Route::get('/{id}', [ClienteController::class, 'show'])->middleware('role:superadmin|ventas');
    Route::put('/{id}', [ClienteController::class, 'update'])->middleware('role:superadmin|ventas');
    Route::delete('/{id}', [ClienteController::class, 'destroy'])->middleware('role:superadmin|ventas');
});
