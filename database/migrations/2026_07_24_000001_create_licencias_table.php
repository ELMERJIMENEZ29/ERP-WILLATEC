<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('licencias')) {
            return;
        }

        Schema::create('licencias', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('empresa');
            $table->string('producto');
            $table->unsignedInteger('cantidad')->default(1);
            $table->unsignedInteger('suscripcion_meses')->default(12);
            $table->string('correo_licencia')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_renovacion');
            $table->timestamps();

            $table->index('cliente_id');
            $table->index('empresa');
            $table->index('producto');
            $table->index('correo_licencia');
            $table->index('fecha_renovacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licencias');
    }
};
