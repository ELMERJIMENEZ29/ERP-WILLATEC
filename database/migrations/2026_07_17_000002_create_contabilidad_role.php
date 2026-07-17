<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'contabilidad')->doesntExist()) {
            DB::table('roles')->insert([
                'name' => 'contabilidad',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('name', 'contabilidad')->first();

        if (! $role) {
            return;
        }

        $hasUsers = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->exists();

        if (! $hasUsers) {
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};
