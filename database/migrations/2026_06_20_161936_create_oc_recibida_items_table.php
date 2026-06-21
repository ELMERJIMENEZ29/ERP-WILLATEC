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
        Schema::create('oc_recibida_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oc_recibida_id')->constrained('oc_recibidas')->cascadeOnDelete();
            $table->foreignId('cotizacion_item_id')->constrained('cotizacion_items')->cascadeOnDelete();
            $table->text('descripcion');
            $table->string('codigo')->nullable();
            $table->string('marca')->nullable();
            $table->string('unidad_medida')->nullable();
            $table->unsignedInteger('cantidad_cotizada');
            $table->unsignedInteger('cantidad_recibida')->default(0);
            $table->boolean('seleccionado')->default(false);
            $table->boolean('comprado')->default(false);
            $table->boolean('entregado')->default(false);
            $table->timestamps();

            $table->unique(['oc_recibida_id', 'cotizacion_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_recibida_items');
    }
};
