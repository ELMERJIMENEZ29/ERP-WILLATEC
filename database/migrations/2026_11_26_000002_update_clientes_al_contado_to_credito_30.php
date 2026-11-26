<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clientes')
            ->where('forma_pago', 'AL CONTADO')
            ->update(['forma_pago' => 'CRÉDITO 30 DÍAS']);
    }

    public function down(): void
    {
        // No se revierte automáticamente porque no se puede distinguir entre
        // valores antiguos y cambios realizados después de la migración.
    }
};
