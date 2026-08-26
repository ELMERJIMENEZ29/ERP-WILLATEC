<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licitacion_cotizaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('licitacion_cotizaciones', 'origen')) {
                $table->string('origen', 30)->default('vinculada');
            }

            if (! Schema::hasColumn('licitacion_cotizaciones', 'creado_por_id')) {
                $table->foreignId('creado_por_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('licitacion_cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('licitacion_cotizaciones', 'creado_por_id')) {
                $table->dropConstrainedForeignId('creado_por_id');
            }

            if (Schema::hasColumn('licitacion_cotizaciones', 'origen')) {
                $table->dropColumn('origen');
            }
        });
    }
};
