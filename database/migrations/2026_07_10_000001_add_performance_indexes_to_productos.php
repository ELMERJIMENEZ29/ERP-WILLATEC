<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['activo', 'created_at'], 'productos_activo_created_idx');
            $table->index(['activo', 'categoria_id'], 'productos_activo_categoria_idx');
            $table->index('codigo', 'productos_codigo_idx');
            $table->index('marca', 'productos_marca_idx');
            $table->index('modelo', 'productos_modelo_idx');
            $table->index('serie', 'productos_serie_idx');
            $table->index('factura_numero', 'productos_factura_numero_idx');
        });

        Schema::table('producto_series', function (Blueprint $table) {
            $table->index('serie', 'producto_series_serie_idx');
        });
    }

    public function down(): void
    {
        Schema::table('producto_series', function (Blueprint $table) {
            $table->dropIndex('producto_series_serie_idx');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_factura_numero_idx');
            $table->dropIndex('productos_serie_idx');
            $table->dropIndex('productos_modelo_idx');
            $table->dropIndex('productos_marca_idx');
            $table->dropIndex('productos_codigo_idx');
            $table->dropIndex('productos_activo_categoria_idx');
            $table->dropIndex('productos_activo_created_idx');
        });
    }
};
