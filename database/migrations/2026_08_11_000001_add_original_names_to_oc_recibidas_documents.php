<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            if (! Schema::hasColumn('oc_recibidas', 'orden_compra_cliente_nombre_original')) {
                $table->string('orden_compra_cliente_nombre_original')->nullable()->after('orden_compra_cliente_path');
            }

            if (! Schema::hasColumn('oc_recibidas', 'guia_emision_nombre_original')) {
                $table->string('guia_emision_nombre_original')->nullable()->after('guia_emision_path');
            }

            if (! Schema::hasColumn('oc_recibidas', 'factura_nombre_original')) {
                $table->string('factura_nombre_original')->nullable()->after('factura_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oc_recibidas', function (Blueprint $table): void {
            foreach ([
                'orden_compra_cliente_nombre_original',
                'guia_emision_nombre_original',
                'factura_nombre_original',
            ] as $column) {
                if (Schema::hasColumn('oc_recibidas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
