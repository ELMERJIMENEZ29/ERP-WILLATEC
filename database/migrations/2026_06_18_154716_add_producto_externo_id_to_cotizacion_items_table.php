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
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->foreignId('producto_externo_id')
                ->nullable()
                ->after('producto_id')
                ->constrained('productos_externos')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_externo_id');
        });
    }
};
