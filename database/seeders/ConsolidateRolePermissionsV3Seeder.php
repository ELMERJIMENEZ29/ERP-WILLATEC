<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV3Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_catalog_v3';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        $children = [
            'cotizaciones.crear', 'cotizaciones.editar', 'cotizaciones.eliminar',
            'cotizaciones.items', 'cotizaciones.costos', 'cotizaciones.revision',
            'cotizaciones.aprobar', 'cotizaciones.rechazar', 'cotizaciones.delegar',
            'cotizaciones.exportar', 'cotizaciones.oc',
        ];

        foreach ($children as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $allCommercialActions = array_merge(['cotizaciones'], $children);
        $assignments = [
            'superadmin' => $allCommercialActions,
            // Conserva el acceso operativo que tenia antes de granularizar rutas.
            'admin' => $allCommercialActions,
            'ventas' => $allCommercialActions,
            'contabilidad' => ['cotizaciones', 'cotizaciones.oc'],
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
