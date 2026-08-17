<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_por_cobrar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('oc_recibida_id')->nullable()->constrained('oc_recibidas')->nullOnDelete();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('monto_cobrado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->string('estado', 30)->default('pendiente');
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('comprobante_id');
            $table->index(['estado', 'fecha_vencimiento']);
            $table->index('cliente_id');
            $table->index('oc_recibida_id');
        });

        Schema::create('cobros', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuenta_por_cobrar_id')->constrained('cuentas_por_cobrar')->cascadeOnDelete();
            $table->date('fecha_cobro')->nullable();
            $table->decimal('monto', 14, 2);
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->string('metodo_cobro', 50)->nullable();
            $table->string('referencia', 120)->nullable();
            $table->string('estado', 30)->default('registrado');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('cuenta_por_cobrar_id');
            $table->index(['estado', 'fecha_cobro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros');
        Schema::dropIfExists('cuentas_por_cobrar');
    }
};
