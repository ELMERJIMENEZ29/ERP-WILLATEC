<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->boolean('entrega_multidestino')->default(false)->after('entrega_destino');
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->string('destino_entrega', 150)->nullable()->after('aplica_costos_adicionales');
        });

        Schema::table('cotizacion_costos_adicionales', function (Blueprint $table) {
            $table->string('destino_entrega', 150)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_costos_adicionales', function (Blueprint $table) {
            $table->dropColumn('destino_entrega');
        });

        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropColumn('destino_entrega');
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn('entrega_multidestino');
        });
    }
};
