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
        Schema::create('cotizacion_costos_adicionales', function (Blueprint $table) {
            $table->id();

            // Tipo de costo adicional (ej. envío, instalación, etc.)
            $table->string('tipo',50);

            // Descripción del costo adicional
            $table->text('descripcion')->nullable();

            // Monto del costo adicional
            $table->decimal('monto', 12, 2)->default(0);

            // Relaciones
            $table->foreignId('cotizacion_id')
                ->constrained()
                ->cascadeOnDelete();
            
            $table->timestamps();
            
            $table->index('cotizacion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_costos_adicionales');
    }
};
