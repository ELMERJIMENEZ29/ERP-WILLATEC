<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->string('categoria_gasto', 80)->nullable()->after('tipo_operacion');
            $table->string('centro_costo', 120)->nullable()->after('categoria_gasto');
            $table->string('fecha_vencimiento_origen', 30)->nullable()->after('fecha_vencimiento');
            $table->decimal('detraccion_porcentaje', 8, 4)->nullable()->after('total');
            $table->decimal('detraccion_monto_original', 14, 2)->default(0)->after('detraccion_porcentaje');
            $table->string('detraccion_codigo', 20)->nullable()->after('detraccion_monto_original');
            $table->string('detraccion_cuenta', 80)->nullable()->after('detraccion_codigo');
            $table->string('xml_path')->nullable()->after('archivo_path');
            $table->index(['tipo_operacion', 'categoria_gasto']);
        });

        foreach (['cuentas_por_pagar', 'cuentas_por_cobrar'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->date('fecha_estimada_pago')->nullable()->after('fecha_vencimiento');
                $table->decimal('detraccion_monto_original', 14, 2)->default(0)->after('saldo');
                $table->decimal('detraccion_monto_programado', 14, 2)->default(0)->after('detraccion_monto_original');
                $table->date('detraccion_fecha_programada')->nullable()->after('detraccion_monto_programado');
                $table->date('detraccion_fecha_pago')->nullable()->after('detraccion_fecha_programada');
                $table->string('estado_operativo', 40)->nullable()->after('estado');
            });
        }

        foreach (['pagos', 'cobros'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('tipo_cambio', 14, 6)->nullable()->after('moneda_id');
                $table->decimal('monto_moneda_cuenta', 14, 2)->nullable()->after('tipo_cambio');
            });
        }

        Schema::create('notas_credito', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->restrictOnDelete();
            $table->string('tipo_anulacion', 20);
            $table->string('serie', 20);
            $table->string('numero', 40);
            $table->date('fecha_emision');
            $table->decimal('monto', 14, 2);
            $table->string('xml_hash', 64)->nullable()->unique();
            $table->string('xml_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->boolean('requiere_devolucion_fisica')->default(false);
            $table->boolean('devolucion_fisica_confirmada')->default(false);
            $table->text('motivo');
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['comprobante_id', 'serie', 'numero']);
        });

        Schema::create('nota_credito_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nota_credito_id')->constrained('notas_credito')->cascadeOnDelete();
            $table->foreignId('comprobante_item_id')->nullable()->constrained('comprobante_items')->nullOnDelete();
            $table->decimal('cantidad', 12, 2)->nullable();
            $table->decimal('monto', 14, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nota_credito_items');
        Schema::dropIfExists('notas_credito');
        foreach (['pagos', 'cobros'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['tipo_cambio', 'monto_moneda_cuenta']));
        }
        foreach (['cuentas_por_pagar', 'cuentas_por_cobrar'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn(['fecha_estimada_pago', 'detraccion_monto_original', 'detraccion_monto_programado', 'detraccion_fecha_programada', 'detraccion_fecha_pago', 'estado_operativo']));
        }
        Schema::table('comprobantes', function (Blueprint $table): void {
            $table->dropIndex(['tipo_operacion', 'categoria_gasto']);
            $table->dropColumn(['categoria_gasto', 'centro_costo', 'fecha_vencimiento_origen', 'detraccion_porcentaje', 'detraccion_monto_original', 'detraccion_codigo', 'detraccion_cuenta', 'xml_path']);
        });
    }
};
