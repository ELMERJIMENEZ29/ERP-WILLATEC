<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedores')) {
            return;
        }

        Schema::create('proveedores', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('ruc', 20)->nullable();
            $table->string('contacto')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('nombre');
            $table->index('ruc');
            $table->index('activo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
