<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventario_movimientos', 'oc_atencion_item_id')) {
                $table->foreignId('oc_atencion_item_id')
                    ->nullable()
                    ->after('referencia_id')
                    ->constrained('oc_atencion_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (Schema::hasColumn('inventario_movimientos', 'oc_atencion_item_id')) {
                $table->dropConstrainedForeignId('oc_atencion_item_id');
            }
        });
    }
};
