<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->string('condicion_pago', 40)->nullable()->after('fecha_vencimiento_origen');
        });
        Schema::table('cuentas_por_pagar', function (Blueprint $table): void {
            $table->string('condicion_pago', 40)->nullable()->after('fecha_estimada_pago');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_por_pagar', fn (Blueprint $table) => $table->dropColumn('condicion_pago'));
        Schema::table('comprobantes', fn (Blueprint $table) => $table->dropColumn('condicion_pago'));
    }
};
