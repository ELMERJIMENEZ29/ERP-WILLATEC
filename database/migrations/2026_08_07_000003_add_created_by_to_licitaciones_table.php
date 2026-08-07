<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('licitaciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('licitaciones', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->after('es_nueva')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('licitaciones', function (Blueprint $table): void {
            if (Schema::hasColumn('licitaciones', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
