<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_item_destinos', function (Blueprint $table) {
            $table->decimal('margen', 5, 2)->nullable()->after('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_item_destinos', function (Blueprint $table) {
            $table->dropColumn('margen');
        });
    }
};
