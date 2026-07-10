<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categorias')->updateOrInsert(
            ['id' => 13],
            [
                'nombre' => 'IMPRESORAS',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('categorias')
            ->where('id', 13)
            ->where('nombre', 'IMPRESORAS')
            ->delete();
    }
};
