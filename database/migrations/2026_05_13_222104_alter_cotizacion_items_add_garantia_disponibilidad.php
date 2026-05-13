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
        Schema::table('cotizacion_items', function (Blueprint $table) {

            /*
        |--------------------------------------------------------------------------
        | GARANTÍA
        |--------------------------------------------------------------------------
        */

            $table->unsignedTinyInteger('garantia_meses')
                ->nullable()
                ->after('precio_venta');

            /*
        |--------------------------------------------------------------------------
        | DISPONIBILIDAD
        |--------------------------------------------------------------------------
        */

            $table->enum('disponibilidad_tipo', [
                'stock',
                'importacion'
            ])
                ->default('stock')
                ->after('garantia_meses');

            $table->unsignedTinyInteger('disponibilidad_dias')
                ->nullable()
                ->after('disponibilidad_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {

            $table->dropColumn([
                'garantia_meses',
                'disponibilidad_tipo',
                'disponibilidad_dias'
            ]);
        });
    }
};
