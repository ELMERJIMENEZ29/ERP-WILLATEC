<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_costos_adicionales', function (Blueprint $table): void {
            $table->longText('imagen_evidencia')->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_costos_adicionales', function (Blueprint $table): void {
            $table->dropColumn('imagen_evidencia');
        });
    }
};
