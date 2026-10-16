<?php

use App\Http\Controllers\Api\AuditoriaController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\EmpresaConfiguracionController;
use App\Http\Controllers\Api\HostingController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\LicenciaController;
use App\Http\Controllers\Api\LicitacionController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\OcAtencionController;
use App\Http\Controllers\Api\OcEmitidaController;
use App\Http\Controllers\Api\OcRecibidaController;
use App\Http\Controllers\Api\OrdenCompraController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\ProductoExternoController;
use App\Http\Controllers\Api\ProductoSkuController;
use App\Http\Controllers\Api\ProveedorController;
use App\Http\Controllers\Api\RequerimientoCompraController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WooCommercePedidoController;
use App\Http\Controllers\Api\WooCommerceProductoController;
use App\Http\Controllers\Api\WooCommerceWebhookController;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function permissionCatalog(): array
{
    return [
        ['key' => 'superadmin', 'module' => 'Administracion', 'label' => 'Control total', 'description' => 'Permisos reservados del superadministrador.'],
        ['key' => 'auditoria', 'module' => 'Administracion', 'label' => 'Auditoria', 'description' => 'Revision de eventos y trazabilidad.'],
        ['key' => 'dashboard', 'module' => 'General', 'label' => 'Dashboard', 'description' => 'Panel principal del sistema.'],
        ['key' => 'productos', 'module' => 'Productos', 'label' => 'Ver productos', 'description' => 'Acceso al modulo de productos.'],
        ['key' => 'productos.crear', 'module' => 'Productos', 'parent' => 'productos', 'label' => 'Crear productos', 'description' => 'Registrar productos nuevos.'],
        ['key' => 'productos.editar', 'module' => 'Productos', 'parent' => 'productos', 'label' => 'Editar productos', 'description' => 'Modificar datos de productos.'],
        ['key' => 'productos.eliminar', 'module' => 'Productos', 'parent' => 'productos', 'label' => 'Eliminar productos', 'description' => 'Eliminar productos.'],
        ['key' => 'productos.normalizar_sku', 'module' => 'Productos', 'parent' => 'productos', 'label' => 'Normalizar SKU', 'description' => 'Previsualizar y aplicar normalizacion de SKU.'],
        ['key' => 'clientes', 'module' => 'Clientes', 'label' => 'CRUD clientes', 'description' => 'Permisos generales de clientes.'],
        ['key' => 'clientes.crear', 'module' => 'Clientes', 'parent' => 'clientes', 'label' => 'Agregar cliente', 'description' => 'Crear clientes nuevos.'],
        ['key' => 'clientes.editar', 'module' => 'Clientes', 'parent' => 'clientes', 'label' => 'Editar cliente', 'description' => 'Actualizar datos de clientes.'],
        ['key' => 'clientes.eliminar', 'module' => 'Clientes', 'parent' => 'clientes', 'label' => 'Eliminar cliente', 'description' => 'Eliminar clientes.'],
        ['key' => 'cotizaciones', 'module' => 'Cotizaciones', 'label' => 'Cotizaciones', 'description' => 'Creacion y gestion comercial.'],
        ['key' => 'cotizaciones.crear', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Crear cotizacion', 'description' => 'Registrar cotizaciones.'],
        ['key' => 'cotizaciones.editar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Editar cotizacion', 'description' => 'Modificar cotizaciones.'],
        ['key' => 'cotizaciones.eliminar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Eliminar cotizacion', 'description' => 'Eliminar cotizaciones.'],
        ['key' => 'cotizaciones.items', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Gestionar items', 'description' => 'Agregar, editar y eliminar items.'],
        ['key' => 'cotizaciones.costos', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Gestionar costos', 'description' => 'Agregar y eliminar costos adicionales.'],
        ['key' => 'cotizaciones.revision', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Enviar a revision', 'description' => 'Enviar cotizaciones y modificaciones a revision.'],
        ['key' => 'cotizaciones.aprobar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Aprobar cotizacion', 'description' => 'Aprobar cotizaciones y modificaciones.'],
        ['key' => 'cotizaciones.rechazar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Rechazar cotizacion', 'description' => 'Rechazar cotizaciones y modificaciones.'],
        ['key' => 'cotizaciones.delegar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Delegar cotizacion', 'description' => 'Delegar cotizaciones.'],
        ['key' => 'cotizaciones.exportar', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Exportar cotizacion', 'description' => 'Generar PDF de cotizaciones.'],
        ['key' => 'cotizaciones.oc', 'module' => 'Cotizaciones', 'parent' => 'cotizaciones', 'label' => 'Registrar OC', 'description' => 'Registrar OC asociadas a cotizaciones.'],
        ['key' => 'licitaciones', 'module' => 'Oportunidades', 'label' => 'Oportunidades', 'description' => 'Seguimiento de licitaciones y privados.'],
        ['key' => 'licitaciones.crear', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Crear oportunidad', 'description' => 'Registrar oportunidades.'],
        ['key' => 'licitaciones.editar', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Editar oportunidad', 'description' => 'Modificar oportunidades.'],
        ['key' => 'licitaciones.eliminar', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Eliminar oportunidad', 'description' => 'Eliminar oportunidades.'],
        ['key' => 'licitaciones.asignar', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Asignar oportunidad', 'description' => 'Asignar oportunidades a ejecutivos.'],
        ['key' => 'licitaciones.comentar', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Comentar oportunidad', 'description' => 'Agregar comentarios.'],
        ['key' => 'licitaciones.archivos', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Gestionar archivos', 'description' => 'Subir y eliminar archivos.'],
        ['key' => 'licitaciones.cotizaciones', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Relacionar cotizaciones', 'description' => 'Vincular cotizaciones.'],
        ['key' => 'licitaciones.cotizaciones_pdf', 'module' => 'Oportunidades', 'parent' => 'licitaciones', 'label' => 'Descargar PDF de cotizacion', 'description' => 'Descargar el PDF de una cotizacion vinculada y aprobada.'],
        ['key' => 'usuarios', 'module' => 'Administracion', 'label' => 'Usuarios', 'description' => 'Administracion de usuarios.'],
        ['key' => 'usuarios.consultar', 'module' => 'Administracion', 'parent' => 'usuarios', 'label' => 'Consultar usuarios', 'description' => 'Ver usuarios del sistema.'],
        ['key' => 'usuarios.crear', 'module' => 'Administracion', 'parent' => 'usuarios', 'label' => 'Crear usuarios', 'description' => 'Registrar usuarios.'],
        ['key' => 'usuarios.editar', 'module' => 'Administracion', 'parent' => 'usuarios', 'label' => 'Editar usuarios', 'description' => 'Actualizar datos de usuarios.'],
        ['key' => 'usuarios.estado', 'module' => 'Administracion', 'parent' => 'usuarios', 'label' => 'Cambiar estado de usuarios', 'description' => 'Activar y desactivar usuarios.'],
        ['key' => 'usuarios.reset_password', 'module' => 'Administracion', 'parent' => 'usuarios', 'label' => 'Restablecer contrasenas', 'description' => 'Restablecer contrasenas de usuarios.'],
        ['key' => 'configuracion', 'module' => 'Administracion', 'label' => 'Configuracion', 'description' => 'Ajustes generales del ERP.'],
        ['key' => 'mensajes', 'module' => 'General', 'label' => 'Mensajes', 'description' => 'Comunicaciones internas.'],
        ['key' => 'ordenes_compra', 'module' => 'Ordenes de compra', 'label' => 'Ordenes de compra', 'description' => 'OC recibidas y emitidas.'],
        ['key' => 'ordenes_compra.consultar', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Consultar OC', 'description' => 'Ver ordenes de compra y atenciones.'],
        ['key' => 'ordenes_compra.crear', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Crear OC', 'description' => 'Registrar ordenes de compra.'],
        ['key' => 'ordenes_compra.items', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Gestionar items OC', 'description' => 'Actualizar items y asociar productos.'],
        ['key' => 'ordenes_compra.editar', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Editar OC', 'description' => 'Modificar ordenes de compra.'],
        ['key' => 'ordenes_compra.cancelar', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Cancelar OC', 'description' => 'Cancelar ordenes de compra.'],
        ['key' => 'ordenes_compra.documentos', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Gestionar documentos OC', 'description' => 'Subir y eliminar documentos.'],
        ['key' => 'ordenes_compra.atenciones', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Gestionar atenciones', 'description' => 'Registrar y confirmar atenciones.'],
        ['key' => 'ordenes_compra.requerimientos', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Gestionar requerimientos', 'description' => 'Consultar y generar requerimientos.'],
        ['key' => 'ordenes_compra.emitir', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Emitir OC', 'description' => 'Registrar y actualizar OC emitidas.'],
        ['key' => 'ordenes_compra.pdf', 'module' => 'Ordenes de compra', 'parent' => 'ordenes_compra', 'label' => 'Ver PDF de OC', 'description' => 'Generar y consultar PDF de OC.'],
        ['key' => 'inventario', 'module' => 'Inventario', 'label' => 'Ver inventario', 'description' => 'Acceso a kardex y movimientos.'],
        ['key' => 'inventario.kardex_entrada', 'module' => 'Inventario', 'parent' => 'inventario', 'label' => 'Registrar entrada Kardex', 'description' => 'Permite crear entradas de inventario.'],
        ['key' => 'inventario.kardex_salida', 'module' => 'Inventario', 'parent' => 'inventario', 'label' => 'Registrar salida Kardex', 'description' => 'Permite crear salidas de inventario.'],
        ['key' => 'inventario.consultar', 'module' => 'Inventario', 'parent' => 'inventario', 'label' => 'Consultar movimientos', 'description' => 'Consultar inventario y movimientos.'],
        ['key' => 'inventario.ajustar', 'module' => 'Inventario', 'parent' => 'inventario', 'label' => 'Ajustar stock', 'description' => 'Ajustar existencias manualmente.'],
        ['key' => 'inventario.documentos', 'module' => 'Inventario', 'parent' => 'inventario', 'label' => 'Gestionar documentos', 'description' => 'Adjuntar documentos a movimientos.'],
        ['key' => 'servicios', 'module' => 'Servicios', 'label' => 'Servicios', 'description' => 'Licencias, hosting y servicios.'],
        ['key' => 'servicios.consultar', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Consultar servicios', 'description' => 'Ver licencias y hostings.'],
        ['key' => 'servicios.crear', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Crear servicios', 'description' => 'Registrar licencias y hostings.'],
        ['key' => 'servicios.editar', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Editar servicios', 'description' => 'Actualizar licencias y hostings.'],
        ['key' => 'servicios.eliminar', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Eliminar servicios', 'description' => 'Eliminar licencias y hostings.'],
        ['key' => 'servicios.renovar', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Renovar servicios', 'description' => 'Renovar licencias y hostings.'],
        ['key' => 'servicios.documentos', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Gestionar documentos de servicios', 'description' => 'Subir y eliminar documentos.'],
        ['key' => 'servicios.cotizaciones', 'module' => 'Servicios', 'parent' => 'servicios', 'label' => 'Relacionar cotizaciones de servicios', 'description' => 'Enlazar y desenlazar cotizaciones.'],
        ['key' => 'soporte_ti', 'module' => 'Soporte TI', 'label' => 'Soporte TI', 'description' => 'Modulo de mesa de ayuda.'],
        ['key' => 'control_pagos', 'module' => 'Control adm', 'label' => 'Control de pagos', 'description' => 'Seguimiento administrativo y contable.'],
        ['key' => 'proveedores', 'module' => 'Productos', 'label' => 'Proveedores', 'description' => 'Consultar y gestionar proveedores.'],
        ['key' => 'proveedores.consultar', 'module' => 'Productos', 'parent' => 'proveedores', 'label' => 'Consultar proveedores', 'description' => 'Ver proveedores registrados.'],
        ['key' => 'proveedores.crear', 'module' => 'Productos', 'parent' => 'proveedores', 'label' => 'Crear proveedores', 'description' => 'Registrar proveedores.'],
        ['key' => 'proveedores.editar', 'module' => 'Productos', 'parent' => 'proveedores', 'label' => 'Editar proveedores', 'description' => 'Actualizar proveedores.'],
        ['key' => 'productos_externos', 'module' => 'Productos', 'label' => 'Productos externos', 'description' => 'Gestionar productos externos y su historial.'],
        ['key' => 'productos_externos.consultar', 'module' => 'Productos', 'parent' => 'productos_externos', 'label' => 'Consultar productos externos', 'description' => 'Ver productos externos e historial.'],
        ['key' => 'productos_externos.convertir', 'module' => 'Productos', 'parent' => 'productos_externos', 'label' => 'Convertir producto externo', 'description' => 'Convertir productos externos a internos.'],
        ['key' => 'woocommerce', 'module' => 'Integraciones', 'label' => 'WooCommerce', 'description' => 'Gestionar pedidos y sincronizacion.'],
        ['key' => 'woocommerce.consultar', 'module' => 'Integraciones', 'parent' => 'woocommerce', 'label' => 'Consultar WooCommerce', 'description' => 'Ver pedidos y registros.'],
        ['key' => 'woocommerce.sincronizar', 'module' => 'Integraciones', 'parent' => 'woocommerce', 'label' => 'Sincronizar WooCommerce', 'description' => 'Ejecutar sincronizaciones.'],
        ['key' => 'woocommerce.reservar', 'module' => 'Integraciones', 'parent' => 'woocommerce', 'label' => 'Reservar pedidos', 'description' => 'Reservar pedidos de WooCommerce.'],
        ['key' => 'woocommerce.productos', 'module' => 'Integraciones', 'parent' => 'woocommerce', 'label' => 'Gestionar productos WooCommerce', 'description' => 'Mapear y sincronizar productos.'],
    ];
}

function ensureRolePermissionsCatalog(): void
{
    foreach (permissionCatalog() as $permission) {
        Permission::firstOrCreate([
            'name' => $permission['key'],
            'guard_name' => 'web',
        ]);
    }
}

function normalizeGrantedPermissions(array $permissions): array
{
    $catalogKeys = array_column(permissionCatalog(), 'key');

    return array_values(array_unique(array_intersect(
        $permissions,
        $catalogKeys
    )));
}

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
Route::post('/superadmin/security-question-reset', [AuthController::class, 'resetPasswordWithSecurityQuestions'])
    ->middleware('throttle:3,1');
Route::post('/woocommerce/webhook/orders', [WooCommerceWebhookController::class, 'orders'])
    ->middleware('throttle:60,1');

Route::post('/two-factor/challenge', [AuthController::class, 'twoFactorChallenge'])
    ->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'token.idle'])->group(function () {

    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::get('/two-factor/qr', [TwoFactorController::class, 'qr']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::delete('/two-factor/disable', [TwoFactorController::class, 'disable']);

    Route::get('/erp/refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1');
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);
    Route::get('/superadmin/security-questions', [AuthController::class, 'securityQuestions'])
        ->middleware('permission:superadmin');
    Route::put('/superadmin/security-questions', [AuthController::class, 'updateSecurityQuestions'])
        ->middleware('permission:superadmin');

    Route::get('/roles', function () {
        ensureRolePermissionsCatalog();

        $roles = Role::with('permissions:id,name')
            ->select('id', 'name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => strtoupper($role->name) === 'SUPERADMIN'
                    ? array_column(permissionCatalog(), 'key')
                    : $role->permissions->pluck('name')->values(),
            ]);

        return response()->json([
            'roles' => $roles,
            'permissions' => permissionCatalog(),
        ]);
    })->middleware('permission:superadmin');

    Route::post('/roles', function () {
        $data = request()->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/', 'unique:roles,name'],
        ]);

        $role = Role::create([
            'name' => strtolower($data['name']),
            'guard_name' => 'web',
        ]);

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => [],
            ],
        ], 201);
    })->middleware('permission:superadmin');

    Route::patch('/roles/{role}', function (Role $role) {
        if (strtoupper($role->name) === 'SUPERADMIN') {
            abort(422, 'El rol superadmin esta protegido.');
        }

        $data = request()->validate([
            'name' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('roles', 'name')->ignore($role->id),
            ],
        ]);

        $role->update(['name' => strtolower($data['name'])]);

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->fresh('permissions:id,name')->permissions->pluck('name')->values(),
            ],
        ]);
    })->middleware('permission:superadmin');

    Route::delete('/roles/{role}', function (Role $role) {
        if (strtoupper($role->name) === 'SUPERADMIN') {
            abort(422, 'El rol superadmin esta protegido.');
        }

        if ($role->users()->exists()) {
            abort(422, 'No se puede eliminar un rol que tiene usuarios asignados.');
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Rol eliminado correctamente.']);
    })->middleware('permission:superadmin');

    Route::put('/roles/{role}/permissions', function (Role $role) {
        ensureRolePermissionsCatalog();

        if (strtoupper($role->name) === 'SUPERADMIN') {
            return response()->json([
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => array_column(permissionCatalog(), 'key'),
                ],
            ]);
        }

        $validPermissions = array_column(permissionCatalog(), 'key');
        $data = request()->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in($validPermissions)],
        ]);

        $permissionNames = normalizeGrantedPermissions(
            $data['permissions'] ?? []
        );

        $permissions = Permission::query()
            ->where('guard_name', $role->guard_name)
            ->whereIn('name', $permissionNames)
            ->get();

        if ($permissions->count() !== count($permissionNames)) {
            abort(422, 'Existen permisos que no corresponden al guard del rol.');
        }

        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->fresh('permissions:id,name')->permissions->pluck('name')->values(),
            ],
        ]);
    })->middleware('permission:superadmin');

    // USUARIOS
    Route::post('/users', [AuthController::class, 'register'])->middleware('permission:usuarios.crear');
    Route::patch('/users/{id}/reset-password', [AuthController::class, 'resetPassword'])->middleware('permission:usuarios.reset_password');

    Route::get('/notifications', [AuthController::class, 'notifications']);
    Route::patch('/notifications/{id}/read', [AuthController::class, 'markNotificationAsRead']);
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::post('/notification-preferences/sound', [NotificationPreferenceController::class, 'uploadSound']);

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:usuarios.consultar');

    Route::get('/empresa-configuracion', [EmpresaConfiguracionController::class, 'show'])->middleware('permission:configuracion');
    Route::put('/empresa-configuracion', [EmpresaConfiguracionController::class, 'update'])
        ->middleware('permission:configuracion');

    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:usuarios.consultar');

    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:usuarios.editar');

    Route::patch('/users/{id}/desactivar', [UserController::class, 'desactivar'])->middleware('permission:usuarios.estado');

    Route::patch('/users/{id}/activar', [UserController::class, 'activar'])->middleware('permission:usuarios.estado');

    // PLATAFORMAS Y PLANTILLAS
    Route::get('/plantillas', [CotizacionController::class, 'indexPlantillas'])
        ->middleware('permission:cotizaciones');

    Route::get('/plataformas', [CotizacionController::class, 'indexPlataformas'])
        ->middleware('permission:cotizaciones');

    Route::get('/auditoria', [AuditoriaController::class, 'index'])
        ->middleware('permission:auditoria');

    Route::get('/inventario/movimientos', [InventarioController::class, 'indexMovimientos'])
        ->middleware('permission:inventario');

    Route::post('/inventario/movimientos/{movimiento}/documento', [InventarioController::class, 'actualizarDocumento'])
        ->middleware('permission:inventario');

    Route::get('/proveedores', [ProveedorController::class, 'index'])
        ->middleware('permission:proveedores.consultar');

    Route::post('/proveedores', [ProveedorController::class, 'store'])
        ->middleware('permission:proveedores.crear');

    Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->middleware('permission:proveedores.editar');
});

Route::prefix('productos')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    // PRODUCTOS
    Route::get('/', [ProductoController::class, 'index'])->middleware('permission:productos');
    Route::get('/sku-normalizacion/preview', [ProductoSkuController::class, 'preview'])->middleware('permission:productos.normalizar_sku');
    Route::post('/sku-normalizacion/apply', [ProductoSkuController::class, 'apply'])->middleware('permission:productos.normalizar_sku');
    Route::get('/{producto}/inventario', [InventarioController::class, 'show'])->middleware('permission:inventario.consultar');
    Route::get('/{producto}/movimientos', [InventarioController::class, 'movimientos'])->middleware('permission:inventario.consultar');
    Route::post('/{producto}/ajustar-stock', [InventarioController::class, 'ajustarStock'])->middleware('permission:inventario.ajustar');
    Route::post('/{producto}/registrar-entrada', [InventarioController::class, 'registrarEntrada'])->middleware('permission:inventario.kardex_entrada');
    Route::post('/{producto}/registrar-salida', [InventarioController::class, 'registrarSalida'])->middleware('permission:inventario.kardex_salida');
    Route::get('/{producto}/historial-cotizaciones', [ProductoController::class, 'historialCotizaciones'])->middleware('permission:productos');
    Route::get('/{id}', [ProductoController::class, 'show'])->middleware('permission:productos');

    Route::post('/', [ProductoController::class, 'store'])->middleware('permission:productos.crear');

    Route::put('/{id}', [ProductoController::class, 'update'])->middleware('permission:productos.editar');

    Route::delete('/{id}', [ProductoController::class, 'destroy'])->middleware('permission:productos.eliminar');
});

Route::post('/upload-imagen', [CotizacionController::class, 'uploadImagen'])
    ->middleware(['auth:sanctum', 'token.idle']);

Route::prefix('productos-externos')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [ProductoExternoController::class, 'index'])
        ->middleware('permission:productos_externos.consultar');
    Route::get('/{productoExterno}/historial-cotizaciones', [ProductoExternoController::class, 'historialCotizaciones'])
        ->middleware('permission:productos_externos.consultar');
    Route::post('/{productoExterno}/convertir-interno', [ProductoExternoController::class, 'convertirAInterno'])
        ->middleware('permission:productos_externos.convertir');
});

Route::prefix('licitaciones')
    ->middleware(['auth:sanctum', 'token.idle'])
    ->group(function () {
        Route::get('/', [LicitacionController::class, 'index'])->middleware('permission:licitaciones');
        Route::post('/', [LicitacionController::class, 'store'])->middleware('permission:licitaciones.crear');
        Route::get('/por-cotizacion/{cotizacion}', [LicitacionController::class, 'showByCotizacion'])->middleware('permission:licitaciones');
        Route::get('/archivos/{archivo}', [LicitacionController::class, 'showArchivo'])->middleware('permission:licitaciones');
        Route::post('/alertas-vencimiento/procesar', [LicitacionController::class, 'procesarAlertasVencimiento'])->middleware('permission:licitaciones');
        Route::get('/{licitacion}', [LicitacionController::class, 'show'])->middleware('permission:licitaciones');
        Route::post('/{licitacion}/registrar-vista', [LicitacionController::class, 'registrarVista'])->middleware('permission:licitaciones');
        Route::put('/{licitacion}', [LicitacionController::class, 'update'])->middleware('permission:licitaciones.editar');
        Route::delete('/{licitacion}', [LicitacionController::class, 'destroy'])->middleware('permission:licitaciones.eliminar');
        Route::post('/{licitacion}/comentarios', [LicitacionController::class, 'addComentario'])->middleware('permission:licitaciones.comentar');
        Route::post('/{licitacion}/archivos', [LicitacionController::class, 'addArchivo'])->middleware('permission:licitaciones.archivos');
        Route::post('/{licitacion}/cotizaciones', [LicitacionController::class, 'addCotizacion'])->middleware('permission:licitaciones.cotizaciones');
        Route::delete('/{licitacion}/archivos/{archivo}', [LicitacionController::class, 'deleteArchivo'])->middleware('permission:licitaciones.archivos');
        Route::delete('/{licitacion}/cotizaciones/{cotizacion}', [LicitacionController::class, 'deleteCotizacion'])->middleware('permission:licitaciones.cotizaciones');
    });

Route::prefix('woocommerce')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/pedidos', [WooCommercePedidoController::class, 'index'])->middleware('permission:woocommerce.consultar');
    Route::post('/pedidos/sincronizar', [WooCommercePedidoController::class, 'sincronizar'])->middleware('permission:woocommerce.sincronizar');
    Route::get('/pedidos/{pedido}', [WooCommercePedidoController::class, 'show'])->middleware('permission:woocommerce.consultar');
    Route::post('/pedidos/{pedido}/reservar', [WooCommercePedidoController::class, 'reservar'])->middleware('permission:woocommerce.reservar');
    Route::post('/productos/mapear', [WooCommerceProductoController::class, 'mapear'])->middleware('permission:woocommerce.productos');
    Route::post('/productos/sync-activos', [WooCommerceProductoController::class, 'sincronizarActivos'])->middleware('permission:woocommerce.productos');
    Route::post('/productos/{producto}/mapear-sku', [WooCommerceProductoController::class, 'mapearPorSku'])->middleware('permission:woocommerce.productos');
    Route::post('/productos/{producto}/sync-stock', [WooCommerceProductoController::class, 'sincronizarStock'])->middleware('permission:woocommerce.productos');
    Route::get('/sync-logs', [WooCommerceProductoController::class, 'logs'])->middleware('permission:woocommerce.consultar');
});

Route::prefix('cotizaciones')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    // â”€â”€ RUTAS ESTÃTICAS PRIMERO â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/', [CotizacionController::class, 'index'])
        ->middleware('permission:cotizaciones');

    Route::post('/', [CotizacionController::class, 'store'])
        ->middleware('permission:cotizaciones.crear');

    Route::post('/completa', [CotizacionController::class, 'storeCompleta'])->middleware('permission:cotizaciones.crear');

    Route::get('/items', [CotizacionController::class, 'indexItems'])
        ->middleware('permission:cotizaciones');

    Route::get('/items/all', [CotizacionController::class, 'allItems'])
        ->middleware('permission:cotizaciones');

    Route::get('/estados', [CotizacionController::class, 'indexEstadoCotizacion'])
        ->middleware('permission:cotizaciones');

    Route::get('/monedas', [CotizacionController::class, 'indexMonedas'])
        ->middleware('permission:cotizaciones');

    Route::get('/resumen-pendientes-revision', [CotizacionController::class, 'resumenPendientesRevision'])
        ->middleware('permission:cotizaciones');

    Route::get('/modificaciones/{modificacion}', [CotizacionController::class, 'showModificacion'])
        ->middleware('permission:cotizaciones');

    Route::put('/modificaciones/{modificacion}', [CotizacionController::class, 'updateModificacion'])
        ->middleware('permission:cotizaciones.editar');

    Route::patch('/modificaciones/{modificacion}/enviar-revision', [CotizacionController::class, 'enviarModificacionRevision'])
        ->middleware('permission:cotizaciones.revision');

    Route::patch('/modificaciones/{modificacion}/aprobar', [CotizacionController::class, 'aprobarModificacion'])
        ->middleware('permission:cotizaciones.aprobar');

    Route::patch('/modificaciones/{modificacion}/rechazar', [CotizacionController::class, 'rechazarModificacion'])
        ->middleware('permission:cotizaciones.rechazar');

    // â”€â”€ RUTAS DINÃMICAS DESPUÃ‰S â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::get('/{id}', [CotizacionController::class, 'show'])
        ->middleware('permission:cotizaciones');

    Route::delete('/{cotizacion}', [CotizacionController::class, 'destroy'])
        ->middleware('permission:cotizaciones.eliminar');

    Route::put('/{id}', [CotizacionController::class, 'update'])
        ->middleware('permission:cotizaciones.editar');

    Route::put('/{id}/completa', [CotizacionController::class, 'updateCompleta'])
        ->middleware('permission:cotizaciones.editar');

    Route::patch('/{id}/recalcular', [CotizacionController::class, 'recalcular'])
        ->middleware('permission:cotizaciones.editar');

    Route::patch('/{cotizacion}/delegar', [CotizacionController::class, 'delegar'])
        ->middleware('permission:cotizaciones.delegar');

    Route::patch('/{cotizacion}/enviar-revision', [CotizacionController::class, 'enviarRevision'])
        ->middleware('permission:cotizaciones.revision');

    Route::get('/{id}/historial', [CotizacionController::class, 'historial'])
        ->middleware('permission:cotizaciones');

    Route::get('/{cotizacion}/versiones', [CotizacionController::class, 'versiones'])
        ->middleware('permission:cotizaciones');

    Route::post('/{cotizacion}/solicitar-modificacion', [CotizacionController::class, 'solicitarModificacion'])
        ->middleware('permission:cotizaciones.revision');

    Route::patch('/{cotizacion}/aprobar', [CotizacionController::class, 'aprobar'])
        ->middleware('permission:cotizaciones.aprobar');

    Route::patch('/{cotizacion}/rechazar', [CotizacionController::class, 'rechazar'])
        ->middleware('permission:cotizaciones.rechazar');

    Route::get('/{cotizacion}/exportar-pdf', [CotizacionController::class, 'exportarPdf'])
        ->middleware('permission:cotizaciones.exportar|licitaciones.cotizaciones_pdf|licitaciones.cotizaciones|licitaciones');

    Route::patch('/{id}/items/orden', [CotizacionController::class, 'reorderItems'])
        ->middleware('permission:cotizaciones.items');

    Route::get('/{cotizacion}/oc-recibida/preview', [OcRecibidaController::class, 'preview'])
        ->middleware('permission:cotizaciones.oc');

    Route::get('/{cotizacion}/oc-emitida/preview', [OcEmitidaController::class, 'preview'])
        ->middleware('permission:cotizaciones.oc');

    Route::get('/{cotizacion}/oc-emitida/items', [OcEmitidaController::class, 'itemsPorProveedorResponse'])
        ->middleware('permission:cotizaciones.oc');

    // Items
    Route::post('/{id}/items', [CotizacionController::class, 'addItem'])
        ->middleware('permission:cotizaciones.items');

    Route::put('/items/{id}', [CotizacionController::class, 'updateItem'])
        ->middleware('permission:cotizaciones.items');

    Route::post('/items/{id}', [CotizacionController::class, 'updateItem'])
        ->middleware('permission:cotizaciones.items');

    Route::delete('/items/{id}', [CotizacionController::class, 'deleteItem'])
        ->middleware('permission:cotizaciones.items');

    // Costos
    Route::post('/{id}/costos', [CotizacionController::class, 'addCosto'])
        ->middleware('permission:cotizaciones.costos');

    Route::delete('/costos/{id}', [CotizacionController::class, 'deleteCosto'])
        ->middleware('permission:cotizaciones.costos');
});

Route::prefix('oc-recibidas')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [OcRecibidaController::class, 'index'])->middleware('permission:ordenes_compra.consultar');
    Route::post('/', [OcRecibidaController::class, 'store'])->middleware('permission:ordenes_compra.crear');
    Route::get('/{ocRecibida}', [OcRecibidaController::class, 'show'])->middleware('permission:ordenes_compra.consultar');
    Route::patch('/{ocRecibida}/items', [OcRecibidaController::class, 'updateItems'])->middleware('permission:ordenes_compra.items');
    Route::patch('/{ocRecibida}/items/{item}/asociar-producto', [OcRecibidaController::class, 'asociarProductoInterno'])->middleware('permission:ordenes_compra.items');
    Route::patch('/{ocRecibida}/cancelar', [OcRecibidaController::class, 'cancelar'])->middleware('permission:ordenes_compra.cancelar');
    Route::post('/{ocRecibida}/documentos', [OcRecibidaController::class, 'documentos'])->middleware('permission:ordenes_compra.documentos');
    Route::delete('/{ocRecibida}/documentos/{tipo}', [OcRecibidaController::class, 'eliminarDocumento'])->middleware('permission:ordenes_compra.documentos');
    Route::delete('/{ocRecibida}/documentos-adicionales/{documento}', [OcRecibidaController::class, 'eliminarDocumentoAdicional'])->middleware('permission:ordenes_compra.documentos');
    Route::get('/{ocRecibida}/atenciones', [OcAtencionController::class, 'index'])->middleware('permission:ordenes_compra.atenciones');
    Route::post('/{ocRecibida}/atenciones', [OcAtencionController::class, 'store'])->middleware('permission:ordenes_compra.atenciones');
    Route::get('/{ocRecibida}/requerimientos/faltantes', [RequerimientoCompraController::class, 'faltantes'])->middleware('permission:ordenes_compra.requerimientos');
    Route::post('/{ocRecibida}/requerimientos/generar', [RequerimientoCompraController::class, 'generarDesdeOc'])->middleware('permission:ordenes_compra.requerimientos');
});

Route::prefix('oc-atenciones')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/{ocAtencion}', [OcAtencionController::class, 'show'])->middleware('permission:ordenes_compra.atenciones');
    Route::patch('/{ocAtencion}/confirmar', [OcAtencionController::class, 'confirmar'])->middleware('permission:ordenes_compra.atenciones');
    Route::patch('/{ocAtencion}/cancelar', [OcAtencionController::class, 'cancelar'])->middleware('permission:ordenes_compra.atenciones');
});

Route::prefix('requerimientos-compra')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [RequerimientoCompraController::class, 'index'])->middleware('permission:ordenes_compra.requerimientos');
    Route::post('/', [RequerimientoCompraController::class, 'store'])->middleware('permission:ordenes_compra.requerimientos');
    Route::get('/{requerimientoCompra}', [RequerimientoCompraController::class, 'show'])->middleware('permission:ordenes_compra.requerimientos');
});

Route::prefix('oc-emitidas')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [OcEmitidaController::class, 'index'])->middleware('permission:ordenes_compra.consultar');
    Route::post('/', [OcEmitidaController::class, 'store'])->middleware('permission:ordenes_compra.emitir');
    Route::get('/{ocEmitida}', [OcEmitidaController::class, 'show'])->middleware('permission:ordenes_compra.consultar');
    Route::put('/{ocEmitida}', [OcEmitidaController::class, 'update'])->middleware('permission:ordenes_compra.editar');
    Route::post('/{ocEmitida}/documentos', [OcEmitidaController::class, 'documentos'])->middleware('permission:ordenes_compra.documentos');
    Route::delete('/{ocEmitida}/documentos/{tipo}', [OcEmitidaController::class, 'eliminarDocumento'])->middleware('permission:ordenes_compra.documentos');
    Route::delete('/{ocEmitida}/documentos-adicionales/{documento}', [OcEmitidaController::class, 'eliminarDocumentoAdicional'])->middleware('permission:ordenes_compra.documentos');
    Route::get('/{ocEmitida}/pdf', [OcEmitidaController::class, 'pdf'])->middleware('permission:ordenes_compra.pdf');
});

Route::prefix('ordencompra')->middleware(['auth:sanctum', 'token.idle'])->group(function () {

    Route::get('/', [OrdenCompraController::class, 'index'])->middleware('permission:ordenes_compra.consultar');

    Route::post('/', [OrdenCompraController::class, 'store'])->middleware('permission:ordenes_compra.crear');

    Route::get('{id}', [OrdenCompraController::class, 'show'])->middleware('permission:ordenes_compra');

    Route::patch(
        '{id}/estado',
        [OrdenCompraController::class, 'updateEstado']
    )->middleware('permission:ordenes_compra.editar');

    Route::patch(
        'items/{id}/estado',
        [OrdenCompraController::class, 'updateEstadoItem']
    )->middleware('permission:ordenes_compra.items');
});

Route::prefix('clientes')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [ClienteController::class, 'index'])->middleware('can:clientes');
    Route::post('/', [ClienteController::class, 'store'])->middleware('can:clientes.crear');
    Route::get('/{id}', [ClienteController::class, 'show'])->middleware('can:clientes');
    Route::put('/{id}', [ClienteController::class, 'update'])->middleware('can:clientes.editar');
    Route::delete('/{id}', [ClienteController::class, 'destroy'])->middleware('can:clientes.eliminar');
});

Route::prefix('licencias')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [LicenciaController::class, 'index'])->middleware('permission:servicios.consultar');
    Route::post('/', [LicenciaController::class, 'store'])->middleware('permission:servicios.crear');
    Route::post('/import/preview', [LicenciaController::class, 'previewImport'])->middleware('permission:servicios.crear');
    Route::post('/import/confirm', [LicenciaController::class, 'confirmImport'])->middleware('permission:servicios.crear');
    Route::post('/{licencia}/renovar', [LicenciaController::class, 'renovar'])->middleware('permission:servicios.renovar');
    Route::post('/{licencia}/documentos', [LicenciaController::class, 'documentos'])->middleware('permission:servicios.documentos');
    Route::delete('/{licencia}/documentos/{documento}', [LicenciaController::class, 'eliminarDocumento'])->middleware('permission:servicios.documentos');
    Route::post('/{licencia}/cotizaciones', [LicenciaController::class, 'enlazarCotizacion'])->middleware('permission:servicios.cotizaciones');
    Route::delete('/{licencia}/cotizaciones/{cotizacion}', [LicenciaController::class, 'desenlazarCotizacion'])->middleware('permission:servicios.cotizaciones');
    Route::get('/{licencia}', [LicenciaController::class, 'show'])->middleware('permission:servicios.consultar');
    Route::put('/{licencia}', [LicenciaController::class, 'update'])->middleware('permission:servicios.editar');
    Route::delete('/{licencia}', [LicenciaController::class, 'destroy'])->middleware('permission:servicios.eliminar');
});

Route::prefix('hostings')->middleware(['auth:sanctum', 'token.idle'])->group(function () {
    Route::get('/', [HostingController::class, 'index'])->middleware('permission:servicios.consultar');
    Route::post('/', [HostingController::class, 'store'])->middleware('permission:servicios.crear');
    Route::post('/import/preview', [HostingController::class, 'previewImport'])->middleware('permission:servicios.crear');
    Route::post('/import/confirm', [HostingController::class, 'confirmImport'])->middleware('permission:servicios.crear');
    Route::post('/{hosting}/renovar', [HostingController::class, 'renovar'])->middleware('permission:servicios.renovar');
    Route::post('/{hosting}/documentos', [HostingController::class, 'documentos'])->middleware('permission:servicios.documentos');
    Route::delete('/{hosting}/documentos/{documento}', [HostingController::class, 'eliminarDocumento'])->middleware('permission:servicios.documentos');
    Route::post('/{hosting}/cotizaciones', [HostingController::class, 'enlazarCotizacion'])->middleware('permission:servicios.cotizaciones');
    Route::delete('/{hosting}/cotizaciones/{cotizacion}', [HostingController::class, 'desenlazarCotizacion'])->middleware('permission:servicios.cotizaciones');
    Route::get('/{hosting}', [HostingController::class, 'show'])->middleware('permission:servicios.consultar');
    Route::put('/{hosting}', [HostingController::class, 'update'])->middleware('permission:servicios.editar');
    Route::delete('/{hosting}', [HostingController::class, 'destroy'])->middleware('permission:servicios.eliminar');
});

