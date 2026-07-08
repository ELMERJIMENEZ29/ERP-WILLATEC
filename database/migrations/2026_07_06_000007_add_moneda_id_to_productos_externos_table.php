<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_externos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos_externos', 'moneda_id')) {
                $table->foreignId('moneda_id')
                    ->nullable()
                    ->after('costo_base_referencial')
                    ->constrained('monedas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos_externos', function (Blueprint $table) {
            if (Schema::hasColumn('productos_externos', 'moneda_id')) {
                $table->dropConstrainedForeignId('moneda_id');
            }
        });
    }
};
