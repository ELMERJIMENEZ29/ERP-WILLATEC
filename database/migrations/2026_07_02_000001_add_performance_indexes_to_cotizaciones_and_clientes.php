<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->index(['estado_cotizacion_id', 'fecha'], 'cotizaciones_estado_fecha_idx');
            $table->index(['user_id', 'fecha'], 'cotizaciones_user_fecha_idx');
            $table->index('fecha', 'cotizaciones_fecha_idx');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['estado', 'tipo_cliente_id'], 'clientes_estado_tipo_idx');
            $table->index(['estado', 'nombre'], 'clientes_estado_nombre_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_estado_nombre_idx');
            $table->dropIndex('clientes_estado_tipo_idx');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropIndex('cotizaciones_fecha_idx');
            $table->dropIndex('cotizaciones_user_fecha_idx');
            $table->dropIndex('cotizaciones_estado_fecha_idx');
        });
    }
};
