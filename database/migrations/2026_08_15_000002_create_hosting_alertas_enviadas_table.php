<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hosting_alertas_enviadas')) {
            return;
        }

        Schema::create('hosting_alertas_enviadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hosting_id')->constrained('hostings')->cascadeOnDelete();
            $table->integer('dias_antes');
            $table->string('correo_destino')->nullable();
            $table->string('correo_copia')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['hosting_id', 'dias_antes']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_alertas_enviadas');
    }
};
