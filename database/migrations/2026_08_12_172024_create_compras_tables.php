<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('oc_emitida_id')->nullable()->constrained('oc_emitidas')->nullOnDelete();
            $table->string('modalidad', 30)->default('directa');
            $table->string('estado', 30)->default('borrador');
            $table->date('fecha_compra')->nullable();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->decimal('subtotal_estimado', 14, 2)->nullable();
            $table->decimal('total_estimado', 14, 2)->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('estado');
            $table->index('modalidad');
            $table->index(['proveedor_id', 'estado']);
            $table->index(['fecha_compra', 'estado']);
        });

        Schema::create('compra_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('requerimiento_compra_item_id')->nullable()->constrained('requerimiento_compra_items')->nullOnDelete();
            $table->foreignId('oc_emitida_item_id')->nullable()->constrained('oc_emitida_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('producto_externo_id')->nullable()->constrained('productos_externos')->nullOnDelete();
            $table->text('descripcion');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('cantidad_recibida', 12, 2)->default(0);
            $table->decimal('costo_unitario_estimado', 14, 4)->nullable();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->timestamps();
            $table->index(['compra_id', 'estado']);
            $table->index('requerimiento_compra_item_id');
            $table->index('producto_id');
            $table->index('producto_externo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compras');
        Schema::dropIfExists('compra_items');
    }
};
