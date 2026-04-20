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
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            //Datos General
            $table->string('numero')->unique();
            $table->date('fecha');
            $table->unsignedInteger('validez_dias')->default(10);
            $table->string('modo_distribucion')->default('POR_ITEM'); // POR_ITEM o POR_CANTIDAD
            $table->string('moneda')->default('PEN');

            //Tipo de cambio
            $table->decimal('tipo_cambio', 10, 4);

            //Información Básica
            $table->string('titulo');

            //Calculo de totales
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('ganancia', 12, 2)->default(0);
            $table->decimal('total_gasto', 12, 2)->default(0);

            //Snapshot del cliente
            $table->string('cliente_nombre');
            $table->string('cliente_ruc',11);
            $table->string('cliente_contacto')->nullable();
            $table->string('cliente_telefono',20)->nullable();
            $table->string('cliente_correo',100)->nullable();

            //Relaciones
            $table->foreignId('cliente_id')->constrained()->index();

            $table->foreignId('plantilla_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('estado_cotizacion_id')
                ->constrained('estado_cotizaciones')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('plataforma_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('usuario_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
