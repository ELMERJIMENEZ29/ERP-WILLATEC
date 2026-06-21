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
        Schema::create('oc_recibidas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->date('fecha_recepcion');
            $table->string('estado')->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->string('orden_compra_cliente_path')->nullable();
            $table->string('guia_emision_path')->nullable();
            $table->string('cliente_nombre');
            $table->string('cliente_ruc', 11);
            $table->string('cliente_contacto')->nullable();
            $table->string('cliente_correo')->nullable();
            $table->foreignId('cotizacion_id')->unique()->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['estado', 'fecha_recepcion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_recibidas');
    }
};
