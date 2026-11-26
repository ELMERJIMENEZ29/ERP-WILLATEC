<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV10Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_licitaciones_marcar_atendida_v10';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        $permission = Permission::firstOrCreate([
            'name' => 'licitaciones.marcar_atendida',
            'guard_name' => 'web',
        ]);

        foreach (['superadmin', 'ventas', 'licitacion', 'licitaciones'] as $roleName) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            $role?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
