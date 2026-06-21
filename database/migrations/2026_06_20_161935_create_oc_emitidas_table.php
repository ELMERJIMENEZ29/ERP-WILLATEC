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
        Schema::create('oc_emitidas', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique();
            $table->date('fecha_emision');
            $table->string('estado')->default('emitida');
            $table->string('proveedor');
            $table->text('observaciones')->nullable();
            $table->string('factura_path')->nullable();
            $table->string('comprobante_pago_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('moneda')->default('PEN');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('cliente_nombre');
            $table->string('cliente_ruc', 11);
            $table->string('cliente_contacto')->nullable();
            $table->string('cliente_correo')->nullable();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['cotizacion_id', 'proveedor']);
            $table->index(['estado', 'fecha_emision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oc_emitidas');
    }
};
