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
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('forma_pago')->default('AL CONTADO')->after('validez_dias');
            $table->foreignId('delegado_cotizacion_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('delegado_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delegado_cotizacion_id');
            $table->dropColumn('forma_pago');
        });
    }
};
