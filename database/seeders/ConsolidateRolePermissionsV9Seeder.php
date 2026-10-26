<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV9Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_kardex_reversion_v9';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        $permission = Permission::firstOrCreate([
            'name' => 'inventario.kardex_revertir',
            'guard_name' => 'web',
        ]);

        $logistica = Role::where('name', 'logistica')
            ->where('guard_name', 'web')
            ->first();

        if ($logistica) {
            $logistica->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
