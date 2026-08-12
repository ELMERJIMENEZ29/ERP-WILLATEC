<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oc_atenciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oc_recibida_id')->constrained('oc_recibidas')->cascadeOnDelete();
            $table->string('numero', 30)->unique();
            $table->dateTime('fecha_atencion')->nullable();
            $table->string('estado', 30)->default('borrador');
            $table->string('tipo_atencion', 60)->default('entrega_cliente');
            $table->text('observacion')->nullable();
            $table->foreignId('preparado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('entregado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('fecha_entrega')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['oc_recibida_id', 'estado']);
            $table->index('fecha_atencion');
        });

        Schema::create('oc_atencion_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oc_atencion_id')->constrained('oc_atenciones')->cascadeOnDelete();
            $table->foreignId('oc_recibida_item_id')->constrained('oc_recibida_items')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion')->nullable();
            $table->string('codigo')->nullable();
            $table->string('marca')->nullable();
            $table->string('unidad_medida', 30)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('cantidad_entregada', 12, 2)->default(0);
            $table->foreignId('inventario_movimiento_id')->nullable()->constrained('inventario_movimientos')->nullOnDelete();
            $table->string('estado', 30)->default('pendiente');
            $table->timestamps();

            $table->index(['oc_recibida_item_id', 'estado']);
            $table->index('producto_id');
        });

        Schema::create('oc_atencion_item_producto_serie', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('oc_atencion_item_id')->constrained('oc_atencion_items')->cascadeOnDelete();
            $table->foreignId('producto_serie_id')->constrained('producto_series')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['oc_atencion_item_id', 'producto_serie_id'], 'oc_atencion_item_serie_unique');
            $table->index('producto_serie_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oc_atencion_item_producto_serie');
        Schema::dropIfExists('oc_atencion_items');
        Schema::dropIfExists('oc_atenciones');
    }
};
