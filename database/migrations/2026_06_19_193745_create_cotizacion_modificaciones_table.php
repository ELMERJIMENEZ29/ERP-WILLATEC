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
        Schema::create('cotizacion_modificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cotizacion_id')
                ->constrained('cotizaciones')
                ->cascadeOnDelete();
            $table->foreignId('original_version_id')
                ->nullable()
                ->constrained('cotizacion_versiones')
                ->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('estado')->default('borrador');
            $table->text('motivo');
            $table->json('propuesta');
            $table->foreignId('requested_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('comentario_revision')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['cotizacion_id', 'estado']);
            $table->index(['cotizacion_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacion_modificaciones');
    }
};
