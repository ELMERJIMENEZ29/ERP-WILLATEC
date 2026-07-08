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
        Schema::create('woocommerce_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 50);
            $table->string('direccion', 50);
            $table->string('endpoint')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->text('mensaje_error')->nullable();
            $table->string('referencia_tipo')->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'estado']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woocommerce_sync_logs');
    }
};
