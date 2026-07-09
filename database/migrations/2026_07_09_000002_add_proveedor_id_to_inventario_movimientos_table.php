<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventario_movimientos', 'proveedor_id')) {
                $table->foreignId('proveedor_id')
                    ->nullable()
                    ->after('proveedor')
                    ->constrained('proveedores')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            if (Schema::hasColumn('inventario_movimientos', 'proveedor_id')) {
                $table->dropConstrainedForeignId('proveedor_id');
            }
        });
    }
};
