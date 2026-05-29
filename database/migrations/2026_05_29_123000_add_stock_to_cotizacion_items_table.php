<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cotizacion_items', 'stock')) {
                $table->integer('stock')->default(0)->after('link_proveedor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table) {
            if (Schema::hasColumn('cotizacion_items', 'stock')) {
                $table->dropColumn('stock');
            }
        });
    }
};
