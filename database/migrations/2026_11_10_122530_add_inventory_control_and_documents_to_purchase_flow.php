<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            $table->boolean('controla_series')->default(false)->after('controla_stock');
        });

        DB::table('productos')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('producto_series')->whereColumn('producto_series.producto_id', 'productos.id'))
            ->update(['controla_series' => true]);

        Schema::table('recepciones_compra', function (Blueprint $table): void {
            $table->string('guia_remision_numero', 100)->nullable()->after('fecha_recepcion');
            $table->string('factura_proveedor_numero', 100)->nullable()->after('guia_remision_numero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recepciones_compra', function (Blueprint $table): void {
            $table->dropColumn(['guia_remision_numero', 'factura_proveedor_numero']);
        });

        Schema::table('productos', function (Blueprint $table): void {
            $table->dropColumn('controla_series');
        });
    }
};
