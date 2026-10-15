<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsSeeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_historic_v1';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            $this->command?->info('La consolidacion de permisos ya fue ejecutada.');
            return;
        }

        $catalog = [
            'superadmin', 'auditoria', 'dashboard',
            'productos', 'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.normalizar_sku',
            'clientes', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
            'cotizaciones', 'cotizaciones.crear', 'cotizaciones.editar', 'cotizaciones.oc',
            'licitaciones', 'licitaciones.crear', 'licitaciones.editar', 'licitaciones.eliminar',
            'licitaciones.comentar', 'licitaciones.archivos', 'licitaciones.cotizaciones', 'licitaciones.asignar',
            'usuarios', 'configuracion', 'mensajes',
            'ordenes_compra', 'ordenes_compra.crear',
            'inventario', 'inventario.kardex_entrada', 'inventario.kardex_salida', 'inventario.consultar', 'inventario.ajustar', 'inventario.documentos',
            'servicios', 'soporte_ti', 'control_pagos', 'proveedores', 'productos_externos', 'woocommerce',
        ];

        foreach ($catalog as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $historic = [
            'superadmin' => $catalog,
            'admin' => [
                'proveedores', 'woocommerce', 'inventario', 'inventario.consultar', 'inventario.ajustar', 'inventario.documentos',
                'dashboard', 'productos', 'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.normalizar_sku',
                'clientes', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
                'cotizaciones', 'cotizaciones.crear', 'cotizaciones.editar', 'cotizaciones.oc',
                'licitaciones', 'licitaciones.crear', 'licitaciones.editar', 'licitaciones.eliminar',
                'licitaciones.comentar', 'licitaciones.archivos', 'licitaciones.cotizaciones', 'licitaciones.asignar',
                'usuarios', 'auditoria', 'mensajes',
                'ordenes_compra', 'ordenes_compra.crear', 'servicios', 'control_pagos', 'productos_externos',
            ],
            'ventas' => [
                'clientes', 'clientes.crear', 'cotizaciones', 'cotizaciones.crear',
                'cotizaciones.editar', 'cotizaciones.oc', 'licitaciones', 'licitaciones.crear',
                'licitaciones.editar', 'licitaciones.comentar', 'licitaciones.archivos',
                'licitaciones.cotizaciones', 'licitaciones.asignar', 'productos',
                'ordenes_compra', 'ordenes_compra.crear', 'proveedores', 'productos_externos',
            ],
            'licitacion' => ['licitaciones', 'licitaciones.crear', 'licitaciones.editar', 'licitaciones.eliminar', 'licitaciones.comentar', 'licitaciones.archivos', 'licitaciones.cotizaciones', 'licitaciones.asignar'],
            'licitaciones' => ['licitaciones', 'licitaciones.crear', 'licitaciones.editar', 'licitaciones.eliminar', 'licitaciones.comentar', 'licitaciones.archivos', 'licitaciones.cotizaciones', 'licitaciones.asignar'],
            'soporte' => ['productos', 'mensajes', 'soporte_ti', 'proveedores', 'inventario', 'inventario.consultar'],
            'logistica' => ['productos', 'productos.crear', 'productos.editar', 'productos.eliminar', 'productos.normalizar_sku', 'inventario', 'inventario.kardex_entrada', 'inventario.kardex_salida', 'inventario.consultar', 'inventario.ajustar', 'inventario.documentos', 'proveedores', 'woocommerce'],
            'contabilidad' => ['cotizaciones', 'ordenes_compra'],
        ];

        foreach ($historic as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', array_unique($permissionNames))
                ->get();

            if ($permissions->isNotEmpty()) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
