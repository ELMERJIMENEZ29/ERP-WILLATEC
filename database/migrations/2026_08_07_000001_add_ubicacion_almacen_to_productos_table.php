<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            if (! Schema::hasColumn('productos', 'ubicacion_almacen')) {
                $table->string('ubicacion_almacen')->nullable()->after('factura_numero');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            if (Schema::hasColumn('productos', 'ubicacion_almacen')) {
                $table->dropColumn('ubicacion_almacen');
            }
        });
    }
};
