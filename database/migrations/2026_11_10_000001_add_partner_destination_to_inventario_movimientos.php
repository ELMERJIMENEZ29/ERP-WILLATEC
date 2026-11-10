<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->foreignId('cliente_id')->nullable()->after('proveedor_id')->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre')->nullable()->after('cliente_id');
            $table->string('cliente_ruc', 30)->nullable()->after('cliente_nombre');
            $table->string('receptor_nombre')->nullable()->after('cliente_ruc');
            $table->string('destino_area_sede')->nullable()->after('receptor_nombre');
            $table->text('motivo_detalle')->nullable()->after('destino_area_sede');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table): void {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn([
                'cliente_id',
                'cliente_nombre',
                'cliente_ruc',
                'receptor_nombre',
                'destino_area_sede',
                'motivo_detalle',
            ]);
        });
    }
};
