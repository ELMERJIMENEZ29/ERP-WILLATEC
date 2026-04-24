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
        Schema::create('orden_compras', function (Blueprint $table) {
            $table->id();

            $table->string('numero')->unique();
            $table->date('fecha');

            $table->string('estado')->default('pendiente'); //PENDIENTE, EN PROCESO, COMPLETADO

            $table->text('observaciones')->nullable();
            $table->date('fecha_entrega')->nullable();

            $table->string('moneda')->default('PEN');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            //Snapshot cliente
            $table->string('cliente_nombre');
            $table->string('cliente_ruc', 11);
            $table->string('cliente_contacto');
            $table->string('cliente_correo');

            //Relaciones
            $table->foreignId('cotizacion_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained()->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_compras');
    }
};
