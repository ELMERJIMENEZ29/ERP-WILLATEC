<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hostings')) {
            return;
        }

        Schema::create('hostings', function (Blueprint $table): void {
            $table->id();
            $table->string('empresa');
            $table->string('ruc', 20)->nullable();
            $table->string('dominio');
            $table->string('plan');
            $table->string('suscripcion', 20)->default('ANUAL');
            $table->date('fecha_inicio');
            $table->date('fecha_renovacion');
            $table->string('contacto')->nullable();
            $table->string('cliente')->nullable();
            $table->timestamps();

            $table->index('empresa');
            $table->index('ruc');
            $table->index('dominio');
            $table->index('suscripcion');
            $table->index('fecha_renovacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostings');
    }
};
