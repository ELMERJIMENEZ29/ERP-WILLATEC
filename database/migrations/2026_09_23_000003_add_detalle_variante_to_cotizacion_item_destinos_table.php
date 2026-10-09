<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_item_destinos', function (Blueprint $table) {
            $table->string('detalle_variante', 255)->nullable()->after('destino_entrega');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_item_destinos', function (Blueprint $table) {
            $table->dropColumn('detalle_variante');
        });
    }
};
