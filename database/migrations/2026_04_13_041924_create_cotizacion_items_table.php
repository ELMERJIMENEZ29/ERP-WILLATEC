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
        Schema::create('cotizacion_items', function (Blueprint $table) {
            $table->id();
            $table->text('descripcion');
            $table->unsignedInteger('cantidad')->default(1);
            $table->string('marca')->nullable();
            $table->string('codigo')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->text('disponibilidad')->nullable();

            //Costos
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('margen', 5, 2)->default(0); // %
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('costo_base', 12, 2)->default(0);

            $table->decimal('subtotal', 12, 2);
            $table->string('imagen')->nullable();

            $table->unsignedInteger('orden')->default(1);

            //Relaciones
            $table->foreignId('cotizacion_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('producto_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('estado_cotizacion_item_id')
                ->default(1) // Asumiendo que el estado "Pendiente" tiene ID 1
                ->constrained('estado_cotizacion_items')
                ->restrictOnDelete();

            // Índice para mantener el orden de los items dentro de una cotización
            $table->index(['cotizacion_id', 'orden']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_items');
    }
};
