<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_por_pagar', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('monto_pagado', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->string('estado', 30)->default('pendiente');
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('comprobante_id');
            $table->index(['estado', 'fecha_vencimiento']);
            $table->index('proveedor_id');
            $table->index('compra_id');
        });

        Schema::create('pagos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cuenta_por_pagar_id')->constrained('cuentas_por_pagar')->cascadeOnDelete();
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto', 14, 2);
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->string('metodo_pago', 50)->nullable();
            $table->string('referencia', 120)->nullable();
            $table->string('estado', 30)->default('registrado');
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('cuenta_por_pagar_id');
            $table->index(['estado', 'fecha_pago']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('cuentas_por_pagar');
    }
};
