<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV5Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_catalog_v5';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        $groups = [
            'usuarios' => ['consultar', 'crear', 'editar', 'estado', 'reset_password'],
            'proveedores' => ['consultar', 'crear', 'editar'],
            'productos_externos' => ['consultar', 'convertir'],
            'woocommerce' => ['consultar', 'sincronizar', 'reservar', 'productos'],
            'servicios' => ['consultar', 'crear', 'editar', 'eliminar', 'renovar', 'documentos', 'cotizaciones'],
        ];

        foreach ($groups as $parent => $children) {
            foreach ($children as $child) {
                Permission::firstOrCreate([
                    'name' => "{$parent}.{$child}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $permissions = fn (string $parent, array $children): array => array_merge(
            [$parent],
            array_map(fn (string $child) => "{$parent}.{$child}", $children),
        );

        $assignments = [
            'superadmin' => array_merge(
                $permissions('usuarios', $groups['usuarios']),
                $permissions('proveedores', $groups['proveedores']),
                $permissions('productos_externos', $groups['productos_externos']),
                $permissions('woocommerce', $groups['woocommerce']),
                $permissions('servicios', $groups['servicios']),
            ),
            'admin' => array_merge(
                $permissions('usuarios', $groups['usuarios']),
                $permissions('proveedores', $groups['proveedores']),
                $permissions('productos_externos', $groups['productos_externos']),
                $permissions('woocommerce', $groups['woocommerce']),
                $permissions('servicios', $groups['servicios']),
            ),
            'ventas' => array_merge(
                $permissions('proveedores', $groups['proveedores']),
                $permissions('productos_externos', $groups['productos_externos']),
            ),
            'soporte' => $permissions('proveedores', $groups['proveedores']),
            'logistica' => array_merge(
                $permissions('proveedores', $groups['proveedores']),
                $permissions('woocommerce', $groups['woocommerce']),
            ),
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $role->givePermissionTo(
                Permission::where('guard_name', 'web')->whereIn('name', $permissionNames)->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
