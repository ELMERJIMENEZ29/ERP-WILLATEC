<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->string('pagado_por')->nullable()->after('metodo_pago');
            $table->decimal('monto_desembolsado', 14, 2)->nullable()->after('monto_moneda_cuenta');
            $table->unsignedSmallInteger('numero_cuotas')->nullable()->after('monto_desembolsado');
        });
        Schema::table('cuentas_por_pagar', function (Blueprint $table): void {
            $table->string('tipo_cuenta', 30)->default('proveedor')->after('id');
            $table->foreignId('cuenta_origen_id')->nullable()->after('tipo_cuenta')->constrained('cuentas_por_pagar')->nullOnDelete();
            $table->foreignId('pago_origen_id')->nullable()->unique()->after('cuenta_origen_id')->constrained('pagos')->nullOnDelete();
            $table->string('beneficiario')->nullable()->after('proveedor_id');
            $table->decimal('comision_origen', 14, 2)->default(0)->after('saldo');
            $table->unsignedSmallInteger('numero_cuotas')->nullable()->after('comision_origen');
            $table->index(['tipo_cuenta', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_por_pagar', function (Blueprint $table): void {
            $table->dropIndex(['tipo_cuenta', 'estado']);
            $table->dropConstrainedForeignId('pago_origen_id');
            $table->dropConstrainedForeignId('cuenta_origen_id');
            $table->dropColumn(['tipo_cuenta', 'beneficiario', 'comision_origen', 'numero_cuotas']);
        });
        Schema::table('pagos', fn (Blueprint $table) => $table->dropColumn(['pagado_por', 'monto_desembolsado', 'numero_cuotas']));
    }
};
