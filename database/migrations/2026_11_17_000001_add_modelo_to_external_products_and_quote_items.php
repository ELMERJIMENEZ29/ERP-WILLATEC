<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_externos', function (Blueprint $table): void {
            if (! Schema::hasColumn('productos_externos', 'modelo')) {
                $table->string('modelo')->nullable()->after('marca');
            }
        });

        Schema::table('cotizacion_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizacion_items', 'modelo')) {
                $table->string('modelo')->nullable()->after('marca');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizacion_items', function (Blueprint $table): void {
            if (Schema::hasColumn('cotizacion_items', 'modelo')) {
                $table->dropColumn('modelo');
            }
        });

        Schema::table('productos_externos', function (Blueprint $table): void {
            if (Schema::hasColumn('productos_externos', 'modelo')) {
                $table->dropColumn('modelo');
            }
        });
    }
};
