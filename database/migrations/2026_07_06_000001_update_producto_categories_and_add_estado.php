<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'estado')) {
            Schema::table('productos', function (Blueprint $table) {
                if (Schema::getConnection()->getDriverName() === 'mysql') {
                    $table->string('estado', 20)->default('nuevo')->after('activo');
                } else {
                    $table->string('estado', 20)->default('nuevo');
                }
            });
        }

        $now = now();
        $categorias = [
            1 => 'LAPTOPS',
            2 => 'ACCESORIOS',
            3 => 'PERIFÉRICOS',
            4 => 'COMPUTADORAS',
            5 => 'LICENCIAS',
            6 => 'SERVIDORES',
            7 => 'GADGETS',
            8 => 'SUMINISTROS',
            9 => 'REDES',
            10 => 'SEGURIDAD',
            11 => 'COMPONENTES',
            12 => 'ALMACENAMIENTO',
        ];

        foreach ($categorias as $id => $nombre) {
            $exists = DB::table('categorias')->where('id', $id)->exists();

            if ($exists) {
                DB::table('categorias')
                    ->where('id', $id)
                    ->update([
                        'nombre' => $nombre,
                        'updated_at' => $now,
                    ]);

                continue;
            }

            DB::table('categorias')->insert([
                'id' => $id,
                'nombre' => $nombre,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('productos', 'estado')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }
};
