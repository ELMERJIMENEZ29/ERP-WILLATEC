<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventario_movimientos', 'moneda_id')) {
                $table->foreignId('moneda_id')
                    ->nullable()
                    ->after('costo_unitario')
                    ->constrained('monedas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (Schema::hasColumn('inventario_movimientos', 'moneda_id')) {
                $table->dropConstrainedForeignId('moneda_id');
            }
        });
    }
};
