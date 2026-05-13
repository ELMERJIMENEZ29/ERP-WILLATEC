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
        Schema::table('plantillas', function (Blueprint $table) {
            // Eliminar foreign key
            $table->dropForeign(['moneda_id']);

            // Eliminar columna
            $table->dropColumn('moneda_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plantillas', function (Blueprint $table) {
            $table->foreignId('moneda_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }
};
