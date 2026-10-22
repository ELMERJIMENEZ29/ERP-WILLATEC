<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cotizaciones', 'cuota_mensual_meses')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->unsignedSmallInteger('cuota_mensual_meses')->default(12)->after('cuota_mensual');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'cuota_mensual_meses')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->dropColumn('cuota_mensual_meses');
            });
        }
    }
};
