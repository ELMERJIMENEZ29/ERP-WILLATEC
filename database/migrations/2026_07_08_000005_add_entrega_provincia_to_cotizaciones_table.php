<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('cotizaciones', 'entrega_provincia')) {
                $table->boolean('entrega_provincia')->default(false)->after('forma_pago');
            }

            if (! Schema::hasColumn('cotizaciones', 'entrega_destino')) {
                $table->string('entrega_destino', 150)->nullable()->after('entrega_provincia');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table): void {
            foreach (['entrega_destino', 'entrega_provincia'] as $column) {
                if (Schema::hasColumn('cotizaciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
