<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cotizaciones', 'cuota_mensual')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->boolean('cuota_mensual')->default(false)->after('adelanto_porcentaje');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cotizaciones', 'cuota_mensual')) {
            Schema::table('cotizaciones', function (Blueprint $table): void {
                $table->dropColumn('cuota_mensual');
            });
        }
    }
};
