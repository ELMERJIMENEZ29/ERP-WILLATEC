<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\OcEmitidaController;
use App\Http\Controllers\Api\OcRecibidaController;
use App\Http\Controllers\Api\OrdenCompraController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProductoExternoController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WooCommerceProductoController;
use App\Http\Controllers\Api\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/superadmin/security-question-reset', [AuthController::class, 'resetPasswordWithSecurityQuestions'])
    ->middleware('throttle:3,1');
Route::post('/woocommerce/webhook/orders', [WooCommerceWebhookController::class, 'orders'])
    ->middleware('throttle:60,1');

Route::post('/two-factor/challenge', [AuthController::class, 'twoFactorChallenge'])
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::get('/two-factor/qr', [TwoFactorController::class, 'qr']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable']);

    Route::get('/erp/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
    Route::get('/superadmin/security-questions', [AuthController::class, 'securityQuestions'])
        ->middleware('role:superadmin');
    Route::put('/superadmin/security-questions', [AuthController::class, 'updateSecurityQuestions'])
        ->middleware('role:superadmin');

    Route::get('/roles', function () {
        return Role::select('id', 'name')->get();
    });

    // USUARIOS
    Route::post('/users', [AuthController::class, 'register'])->middleware('role:superadmin|admin');
    Route::patch('/users/{id}/reset-password', [AuthController::class, 'resetPassword'])->middleware('role:superadmin');

    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::patch('/notifications/{id}/read', [AuthController::class, 'markNotificationAsRead']);

    Route::get('/users', [UserController::class, 'index']);

    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('role:superadmin|admin');

    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('role:superadmin');

    Route::patch('/users/{id}/desactivar', [UserController::class, 'desactivar'])->middleware('role:superadmin');

    Route::patch('/users/{id}/activar', [UserController::class, 'activar'])->middleware('role:superadmin');

    // PLATAFORMAS Y PLANTILLAS
    Route::get('/plantillas', [CotizacionController::class, 'indexPlantillas'])
        ->middleware('role:superadmin|ventas');

    Route::get('/plataformas', [CotizacionController::class, 'indexPlataformas'])
        ->middleware('role:superadmin|ventas');

    Route::get('/auditoria', [AuditoriaController::class, 'index'])
        ->middleware('role:superadmin|admin');

    Route::get('/inventario/movimientos', [InventarioController::class, 'indexMovimientos'])
        ->middleware('role:superadmin|logistica');

    Route::get('/proveedores', [ProveedorController::class, 'index'])
        ->middleware('role:superadmin|admin|ventas|soporte|logistica');

    Route::post('/proveedores', [ProveedorController::class, 'store'])
        ->middleware('role:superadmin|admin|ventas|soporte|logistica');

    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->middleware('role:superadmin|admin|ventas|soporte|logistica');
});

Route::prefix('productos')->middleware('auth:sanctum')->group(function () {
    // PRODUCTOS
    Route::get('/', [ProductoController::class, 'index']);
    Route::get('/{producto}/inventario', [InventarioController::class, 'show'])->middleware('role:superadmin|admin|soporte|logistica');
    Route::get('/{producto}/movimientos', [InventarioController::class, 'movimientos'])->middleware('role:superadmin|admin|soporte|logistica');
    Route::post('/{producto}/ajustar-stock', [InventarioController::class, 'ajustarStock'])->middleware('role:superadmin|admin|soporte|logistica');
    Route::post('/{producto}/registrar-entrada', [InventarioController::class, 'registrarEntrada'])->middleware('role:superadmin|admin|soporte|logistica');
    Route::post('/{producto}/registrar-salida', [InventarioController::class, 'registrarSalida'])->middleware('role:superadmin|admin|soporte|logistica');
    Route::get('/{id}', [ProductoController::class, 'show']);

    Route::post('/', [ProductoController::class, 'store'])->middleware('role:superadmin|ventas|admin|soporte|logistica');

    Route::put('/{id}', [ProductoController::class, 'update'])->middleware('role:superadmin|ventas|admin|soporte|logistica');

    Route::delete('/{id}', [ProductoController::class, 'destroy'])->middleware('role:superadmin|ventas|admin|soporte|logistica');
});

Route::post('/upload-imagen', [CotizacionController::class, 'uploadImagen'])
    ->middleware(['auth:sanctum']);

Route::prefix('productos-externos')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [ProductoExternoController::class, 'index'])
        ->middleware('role:superadmin|ventas');
    Route::post('/{productoExterno}/convertir-interno', [ProductoExternoController::class, 'convertirAInterno'])
        ->middleware('role:superadmin|admin');
});

Route::prefix('woocommerce')->middleware(['auth:sanctum', 'role:superadmin|admin'])->group(function () {
    Route::post('/productos/mapear', [WooCommerceProductoController::class, 'mapear']);
    Route::post('/productos/{producto}/sync-stock', [WooCommerceProductoController::class, 'sincronizarStock']);
    Route::get('/sync-logs', [WooCommerceProductoController::class, 'logs']);
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

    Route::get('/items/all', [CotizacionController::class, 'allItems'])
        ->middleware('role:superadmin|ventas');

    Route::get('/estados', [CotizacionController::class, 'indexEstadoCotizacion'])
        ->middleware('role:superadmin|ventas');

    Route::get('/monedas', [CotizacionController::class, 'indexMonedas'])
        ->middleware('role:superadmin|ventas');

    Route::get('/modificaciones/{modificacion}', [CotizacionController::class, 'showModificacion'])
        ->middleware('role:superadmin|ventas');

    Route::put('/modificaciones/{modificacion}', [CotizacionController::class, 'updateModificacion'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/modificaciones/{modificacion}/enviar-revision', [CotizacionController::class, 'enviarModificacionRevision'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/modificaciones/{modificacion}/aprobar', [CotizacionController::class, 'aprobarModificacion'])
        ->middleware('role:superadmin|admin');

    Route::patch('/modificaciones/{modificacion}/rechazar', [CotizacionController::class, 'rechazarModificacion'])
        ->middleware('role:superadmin|admin');

    // ── RUTAS DINÁMICAS DESPUÉS ──────────────────────────────
    Route::get('/{id}', [CotizacionController::class, 'show'])
        ->middleware('role:superadmin|ventas');

    Route::delete('/{cotizacion}', [CotizacionController::class, 'destroy'])
        ->middleware('role:superadmin|ventas');

    Route::put('/{id}', [CotizacionController::class, 'update'])
        ->middleware('role:superadmin|ventas');

    Route::put('/{id}/completa', [CotizacionController::class, 'updateCompleta'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{id}/recalcular', [CotizacionController::class, 'recalcular'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/delegar', [CotizacionController::class, 'delegar'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/enviar-revision', [CotizacionController::class, 'enviarRevision'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{id}/historial', [CotizacionController::class, 'historial'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/versiones', [CotizacionController::class, 'versiones'])
        ->middleware('role:superadmin|ventas');

    Route::post('/{cotizacion}/solicitar-modificacion', [CotizacionController::class, 'solicitarModificacion'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->middleware('role:superadmin|ventas');

    Route::patch('/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/exportar-pdf', [CotizacionController::class, 'exportarPdf'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/oc-recibida/preview', [OcRecibidaController::class, 'preview'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/oc-emitida/preview', [OcEmitidaController::class, 'preview'])
        ->middleware('role:superadmin|ventas');

    Route::get('/{cotizacion}/oc-emitida/items', [OcEmitidaController::class, 'itemsPorProveedorResponse'])
        ->middleware('role:superadmin|ventas');

    // Items
    Route::post('/{id}/items', [CotizacionController::class, 'addItem'])
        ->middleware('role:superadmin|ventas');

    Route::put('/items/{id}', [CotizacionController::class, 'updateItem'])
        ->middleware('role:superadmin|ventas');

    Route::post('/items/{id}', [CotizacionController::class, 'updateItem'])
        ->middleware('role:superadmin|ventas');

    Route::delete('/items/{id}', [CotizacionController::class, 'deleteItem'])
        ->middleware('role:superadmin|ventas');

    // Costos
    Route::post('/{id}/costos', [CotizacionController::class, 'addCosto'])
        ->middleware('role:superadmin|ventas');

    Route::delete('/costos/{id}', [CotizacionController::class, 'deleteCosto'])
        ->middleware('role:superadmin|ventas');
});

Route::prefix('oc-recibidas')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OcRecibidaController::class, 'index'])->middleware('role:superadmin|ventas');
    Route::post('/', [OcRecibidaController::class, 'store'])->middleware('role:superadmin|ventas');
    Route::get('/{ocRecibida}', [OcRecibidaController::class, 'show'])->middleware('role:superadmin|ventas');
    Route::patch('/{ocRecibida}/items', [OcRecibidaController::class, 'updateItems'])->middleware('role:superadmin|ventas');
    Route::post('/{ocRecibida}/documentos', [OcRecibidaController::class, 'documentos'])->middleware('role:superadmin|ventas');
});

Route::prefix('oc-emitidas')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [OcEmitidaController::class, 'index'])->middleware('role:superadmin|ventas');
    Route::post('/', [OcEmitidaController::class, 'store'])->middleware('role:superadmin|ventas');
    Route::get('/{ocEmitida}', [OcEmitidaController::class, 'show'])->middleware('role:superadmin|ventas');
    Route::post('/{ocEmitida}/documentos', [OcEmitidaController::class, 'documentos'])->middleware('role:superadmin|ventas');
    Route::get('/{ocEmitida}/pdf', [OcEmitidaController::class, 'pdf'])->middleware('role:superadmin|ventas');
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
