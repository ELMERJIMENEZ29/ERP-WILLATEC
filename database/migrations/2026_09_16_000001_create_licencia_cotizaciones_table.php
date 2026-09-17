<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencia_cotizaciones')) {
            return;
        }

        Schema::create('licencia_cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['licencia_id', 'cotizacion_id']);
            $table->index('licencia_id');
            $table->index('cotizacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_cotizaciones');
    }
};
