<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizacion_items', 'importacion_calculo')) {
                $table->json('importacion_calculo')->nullable()->after('stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table): void {
            if (Schema::hasColumn('cotizacion_items', 'importacion_calculo')) {
                $table->dropColumn('importacion_calculo');
            }
        });
    }
};
