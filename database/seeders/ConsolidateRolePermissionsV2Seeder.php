<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV2Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_catalog_v2';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            $this->command?->info('La ampliacion del catalogo ya fue ejecutada.');
            return;
        }

        $assignments = [
            'superadmin' => [
                'superadmin', 'productos.eliminar', 'productos.normalizar_sku',
                'inventario.consultar', 'inventario.ajustar', 'inventario.documentos',
                'licitaciones.asignar', 'proveedores', 'productos_externos', 'woocommerce',
            ],
            'admin' => [
                'productos.eliminar', 'productos.normalizar_sku',
                'inventario.consultar', 'inventario.ajustar', 'inventario.documentos',
                'licitaciones.asignar', 'proveedores', 'productos_externos', 'woocommerce',
            ],
            'ventas' => [
                'licitaciones.asignar', 'proveedores', 'productos_externos',
            ],
            'licitacion' => ['licitaciones.asignar'],
            'logistica' => [
                'productos.eliminar', 'productos.normalizar_sku',
                'inventario.consultar', 'inventario.ajustar', 'inventario.documentos',
                'licitaciones.asignar', 'proveedores', 'woocommerce',
            ],
            'soporte' => ['inventario.consultar', 'proveedores'],
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $permissions = collect($permissionNames)->map(
                fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
            );

            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
