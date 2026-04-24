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
        Schema::create('orden_compra_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('orden_compra_id')->constrained()->cascadeOnDelete();

            //REFERENCIA OPCIONAL AL ITEM DE LA COTIZACION
            $table->foreignId('cotizacion_item_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('descripcion');
            $table->string('codigo')->nullable();
            $table->string('marca')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->integer('cantidad');

            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('costo_total', 12, 2);

            $table->string('estado')->default('pendiente'); //PENDIENTE, COMPRADO, ENTREGADO

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_compra_items');
    }
};
