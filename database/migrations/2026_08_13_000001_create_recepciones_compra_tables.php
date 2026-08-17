<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones_compra', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->date('fecha_recepcion')->nullable();
            $table->string('estado', 30)->default('borrador');
            $table->text('observacion')->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmado_en')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index(['compra_id', 'estado']);
            $table->index(['proveedor_id', 'estado']);
        });

        Schema::create('recepcion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recepcion_compra_id')->constrained('recepciones_compra')->cascadeOnDelete();
            $table->foreignId('compra_item_id')->constrained('compra_items')->restrictOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->text('descripcion');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario_provisional', 14, 4)->nullable();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->foreignId('inventario_movimiento_id')->nullable()->constrained('inventario_movimientos')->nullOnDelete();
            $table->timestamps();

            $table->index(['recepcion_compra_id', 'estado']);
            $table->index('compra_item_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_items');
        Schema::dropIfExists('recepciones_compra');
    }
};
