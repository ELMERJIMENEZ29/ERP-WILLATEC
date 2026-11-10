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
        Schema::create('solicitudes_sobrecompra', function (Blueprint $table): void {
            $table->id();
            $table->string('numero')->unique();
            $table->string('estado', 30)->default('pendiente');
            $table->text('justificacion');
            $table->text('comentario_resolucion')->nullable();
            $table->json('payload_compra');
            $table->foreignId('solicitado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resuelto_en')->nullable();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->timestamp('utilizado_en')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_sobrecompra');
    }
};
