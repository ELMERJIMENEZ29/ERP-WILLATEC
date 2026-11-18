<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_alertas_enviadas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cheque_id')->constrained('cheques')->cascadeOnDelete();
            $table->date('fecha_vencimiento');
            $table->unsignedTinyInteger('dias_antes');
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['cheque_id', 'fecha_vencimiento', 'dias_antes'], 'cheque_alerta_vencimiento_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_alertas_enviadas');
    }
};
