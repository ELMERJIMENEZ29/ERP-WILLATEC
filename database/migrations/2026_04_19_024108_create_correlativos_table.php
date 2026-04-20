<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Unique;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('correlativos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo'); // cotizacion, orden_compra, etc
            $table->integer('numero_actual')->default(0);
            $table->integer('anio');
            $table->unique(['tipo', 'anio']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correlativos');
    }
};
