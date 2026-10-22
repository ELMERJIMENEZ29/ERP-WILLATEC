<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV4Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_catalog_v4';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        $children = [
            'ordenes_compra.consultar', 'ordenes_compra.crear', 'ordenes_compra.items',
            'ordenes_compra.editar', 'ordenes_compra.cancelar', 'ordenes_compra.documentos',
            'ordenes_compra.atenciones', 'ordenes_compra.requerimientos',
            'ordenes_compra.emitir', 'ordenes_compra.pdf',
        ];

        foreach ($children as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $all = array_merge(['ordenes_compra'], $children);
        $assignments = [
            'superadmin' => $all,
            'admin' => $all,
            'ventas' => $all,
            'contabilidad' => $all,
            'logistica' => [
                'ordenes_compra', 'ordenes_compra.consultar', 'ordenes_compra.items',
                'ordenes_compra.atenciones', 'ordenes_compra.requerimientos',
            ],
        ];

        foreach ($assignments as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', $permissionNames)
                ->get();
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
