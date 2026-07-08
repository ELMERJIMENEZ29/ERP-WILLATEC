<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_externos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos_externos', 'precio_incluye_igv')) {
                $table->boolean('precio_incluye_igv')
                    ->nullable()
                    ->after('moneda_id');
            }

            if (! Schema::hasColumn('productos_externos', 'plantilla_origen_id')) {
                $table->foreignId('plantilla_origen_id')
                    ->nullable()
                    ->after('precio_incluye_igv')
                    ->constrained('plantillas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos_externos', function (Blueprint $table) {
            if (Schema::hasColumn('productos_externos', 'plantilla_origen_id')) {
                $table->dropConstrainedForeignId('plantilla_origen_id');
            }

            if (Schema::hasColumn('productos_externos', 'precio_incluye_igv')) {
                $table->dropColumn('precio_incluye_igv');
            }
        });
    }
};
