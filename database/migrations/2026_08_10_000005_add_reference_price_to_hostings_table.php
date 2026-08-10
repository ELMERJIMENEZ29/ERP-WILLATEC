<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostings', function (Blueprint $table): void {
            if (! Schema::hasColumn('hostings', 'precio_sin_igv')) {
                $table->decimal('precio_sin_igv', 12, 2)->nullable()->after('plan');
            }

            if (! Schema::hasColumn('hostings', 'moneda_id')) {
                $table->foreignId('moneda_id')
                    ->nullable()
                    ->after('precio_sin_igv')
                    ->constrained('monedas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('hostings', function (Blueprint $table): void {
            if (Schema::hasColumn('hostings', 'moneda_id')) {
                $table->dropConstrainedForeignId('moneda_id');
            }

            if (Schema::hasColumn('hostings', 'precio_sin_igv')) {
                $table->dropColumn('precio_sin_igv');
            }
        });
    }
};
