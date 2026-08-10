<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_item_proveedores', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizacion_item_proveedores', 'proveedor_id')) {
                $table->foreignId('proveedor_id')
                    ->nullable()
                    ->after('cotizacion_item_id')
                    ->constrained('proveedores')
                    ->nullOnDelete();
            }

            $table->index(['proveedor_id', 'nombre'], 'cot_item_prov_catalog_nombre_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_item_proveedores', function (Blueprint $table): void {
            if (Schema::hasColumn('cotizacion_item_proveedores', 'proveedor_id')) {
                $table->dropIndex('cot_item_prov_catalog_nombre_idx');
                $table->dropConstrainedForeignId('proveedor_id');
            }
        });
    }
};
