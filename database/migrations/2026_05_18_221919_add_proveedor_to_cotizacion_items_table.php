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
            $table->string('proveedor')->nullable()->after('tipo');
            $table->text('link_proveedor')->nullable()->after('proveedor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->dropColumn(['proveedor', 'link_proveedor']);
        });
    }
};
