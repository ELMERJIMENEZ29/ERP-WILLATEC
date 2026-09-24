<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizacion_item_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_item_id')
                ->constrained('cotizacion_items')
                ->cascadeOnDelete();
            $table->string('destino_entrega', 150);
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->decimal('ganancia', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['cotizacion_item_id', 'destino_entrega']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_item_destinos');
    }
};
