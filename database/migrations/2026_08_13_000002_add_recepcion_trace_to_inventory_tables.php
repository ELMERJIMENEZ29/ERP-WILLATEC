<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreignId('recepcion_item_id')->nullable()->constrained('recepcion_items')->nullOnDelete();
            $table->string('costo_tipo', 30)->nullable();
            $table->index('recepcion_item_id');
        });

        Schema::table('producto_series', function (Blueprint $table): void {
            $table->foreignId('recepcion_item_id')->nullable()->constrained('recepcion_items')->nullOnDelete();
            $table->index('recepcion_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('producto_series', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recepcion_item_id');
        });

        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recepcion_item_id');
            $table->dropColumn('costo_tipo');
        });
    }
};
