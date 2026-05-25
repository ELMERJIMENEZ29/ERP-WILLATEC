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
        Schema::create('cotizacion_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')
                ->constrained('cotizaciones')
                ->cascadeOnDelete();
            $table->foreignId('estado_anterior_id')
                ->nullable()
                ->constrained('estado_cotizaciones')
                ->restrictOnDelete();
            $table->foreignId('estado_nuevo_id')
                ->constrained('estado_cotizaciones')
                ->restrictOnDelete();
            $table->text('comentario')->nullable();
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();

            $table->index(['cotizacion_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_historial');
    }
};
