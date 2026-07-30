<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostings', function (Blueprint $table): void {
            if (! Schema::hasColumn('hostings', 'cliente_id')) {
                $table->foreignId('cliente_id')->nullable()->after('id')->constrained('clientes')->nullOnDelete();
            }

            if (! Schema::hasColumn('hostings', 'correo_hosting')) {
                $table->string('correo_hosting')->nullable()->after('cliente');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hostings', function (Blueprint $table): void {
            if (Schema::hasColumn('hostings', 'cliente_id')) {
                $table->dropConstrainedForeignId('cliente_id');
            }

            if (Schema::hasColumn('hostings', 'correo_hosting')) {
                $table->dropColumn('correo_hosting');
            }
        });
    }
};
