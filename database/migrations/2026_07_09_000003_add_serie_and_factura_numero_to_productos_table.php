<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            if (! Schema::hasColumn('productos', 'serie')) {
                $table->string('serie', 100)->nullable()->after('codigo_barras');
            }

            if (! Schema::hasColumn('productos', 'factura_numero')) {
                $table->string('factura_numero', 100)->nullable()->after('serie');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table): void {
            foreach (['factura_numero', 'serie'] as $column) {
                if (Schema::hasColumn('productos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
