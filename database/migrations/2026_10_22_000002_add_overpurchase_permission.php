<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'compras.sobrecompra')->value('id');

        if (! $permissionId) {
            DB::table('permissions')->insert([
                'name' => 'compras.sobrecompra',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('name', 'compras.sobrecompra')->value('id');
        }

        foreach (['admin', 'superadmin'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if ($roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'compras.sobrecompra')->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
