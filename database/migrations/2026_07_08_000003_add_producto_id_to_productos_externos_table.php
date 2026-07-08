<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos_externos', function (Blueprint $table): void {
            if (! Schema::hasColumn('productos_externos', 'producto_id')) {
                $table->foreignId('producto_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('productos')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos_externos', function (Blueprint $table): void {
            if (Schema::hasColumn('productos_externos', 'producto_id')) {
                $table->dropConstrainedForeignId('producto_id');
            }
        });
    }
};
