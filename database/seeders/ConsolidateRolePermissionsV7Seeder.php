<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ConsolidateRolePermissionsV7Seeder extends Seeder
{
    private const MIGRATION_KEY = 'roles_permissions_historic_licitacion_v7';

    public function run(): void
    {
        if (DB::table('permission_migrations')->where('key', self::MIGRATION_KEY)->exists()) {
            return;
        }

        foreach (['licitacion', 'licitaciones'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->revokePermissionTo([
                    'clientes', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
                    'productos', 'productos.crear', 'productos.editar', 'productos.eliminar',
                    'productos.normalizar_sku',
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('permission_migrations')->insert([
            'key' => self::MIGRATION_KEY,
            'executed_at' => now(),
        ]);
    }
}
