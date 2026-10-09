<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hosting_cotizaciones')) {
            return;
        }

        Schema::create('hosting_cotizaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hosting_id')->constrained('hostings')->cascadeOnDelete();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hosting_id', 'cotizacion_id']);
            $table->index('hosting_id');
            $table->index('cotizacion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_cotizaciones');
    }
};
