<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            if (! Schema::hasColumn('inventario_movimientos', 'ip_origen')) {
                $table->string('ip_origen', 45)->nullable()->after('observacion');
            }

            if (! Schema::hasColumn('inventario_movimientos', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_origen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            if (Schema::hasColumn('inventario_movimientos', 'user_agent')) {
                $table->dropColumn('user_agent');
            }

            if (Schema::hasColumn('inventario_movimientos', 'ip_origen')) {
                $table->dropColumn('ip_origen');
            }
        });
    }
};
