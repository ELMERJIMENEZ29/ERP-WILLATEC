<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plantillas')
            ->where('nombre', 'GSD PRIVADO')
            ->update([
                'nombre' => 'ALQUILER PRIVADO',
                'formato_pdf' => 'alquiler-privado',
            ]);

        DB::table('plantillas')
            ->where('nombre', 'GSD ESTADO')
            ->update([
                'nombre' => 'ALQUILER ESTADO',
                'formato_pdf' => 'alquiler-estado',
            ]);

        DB::table('plantillas')
            ->where('nombre', 'ALQUILER PRIVADO')
            ->where('formato_pdf', 'gsd-privado')
            ->update(['formato_pdf' => 'alquiler-privado']);

        DB::table('plantillas')
            ->where('nombre', 'ALQUILER ESTADO')
            ->where('formato_pdf', 'gsd-estado')
            ->update(['formato_pdf' => 'alquiler-estado']);
    }

    public function down(): void
    {
        DB::table('plantillas')
            ->where('nombre', 'ALQUILER PRIVADO')
            ->update([
                'nombre' => 'GSD PRIVADO',
                'formato_pdf' => 'gsd-privado',
            ]);

        DB::table('plantillas')
            ->where('nombre', 'ALQUILER ESTADO')
            ->update([
                'nombre' => 'GSD ESTADO',
                'formato_pdf' => 'gsd-estado',
            ]);
    }
};
