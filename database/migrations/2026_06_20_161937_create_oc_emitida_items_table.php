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
        Schema::create('oc_emitida_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oc_emitida_id')->constrained('oc_emitidas')->cascadeOnDelete();
            $table->foreignId('cotizacion_item_id')->constrained('cotizacion_items')->cascadeOnDelete();
            $table->text('descripcion');
            $table->string('codigo')->nullable();
            $table->string('marca')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->string('estado')->default('pendiente');
            $table->timestamps();

            $table->unique(['oc_emitida_id', 'cotizacion_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_emitida_items');
    }
};
