<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizaciones', 'adelanto')) {
                $table->boolean('adelanto')->default(false)->after('forma_pago');
            }

            if (! Schema::hasColumn('cotizaciones', 'adelanto_porcentaje')) {
                $table->decimal('adelanto_porcentaje', 5, 2)->nullable()->after('adelanto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            foreach (['adelanto_porcentaje', 'adelanto'] as $column) {
                if (Schema::hasColumn('cotizaciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
