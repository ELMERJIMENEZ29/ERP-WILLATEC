<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencia_alertas_enviadas')) {
            return;
        }

        Schema::create('licencia_alertas_enviadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            $table->integer('dias_antes');
            $table->string('correo_destino')->nullable();
            $table->string('correo_copia')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['licencia_id', 'dias_antes']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_alertas_enviadas');
    }
};
