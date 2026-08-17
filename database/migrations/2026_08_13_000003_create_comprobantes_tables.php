<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table): void {
            $table->id();
            $table->string('tipo_operacion', 20);
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('oc_recibida_id')->nullable()->constrained('oc_recibidas')->nullOnDelete();
            $table->foreignId('cotizacion_id')->nullable()->constrained('cotizaciones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('emisor_ruc', 20)->nullable();
            $table->string('emisor_nombre')->nullable();
            $table->string('receptor_ruc', 20)->nullable();
            $table->string('receptor_nombre')->nullable();
            $table->string('tipo_comprobante', 10);
            $table->string('serie', 20);
            $table->string('numero', 40);
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->foreignId('moneda_id')->nullable()->constrained('monedas')->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->string('estado', 30)->default('registrado');
            $table->string('xml_hash', 64)->nullable();
            $table->string('archivo_path')->nullable();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['emisor_ruc', 'tipo_comprobante', 'serie', 'numero'], 'comprobantes_logical_unique');
            $table->unique('xml_hash');
            $table->index(['tipo_operacion', 'estado']);
            $table->index('compra_id');
            $table->index('oc_recibida_id');
            $table->index('fecha_emision');
        });

        Schema::create('comprobante_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->foreignId('compra_item_id')->nullable()->constrained('compra_items')->nullOnDelete();
            $table->foreignId('cotizacion_item_id')->nullable()->constrained('cotizacion_items')->nullOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->text('descripcion');
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->decimal('valor_unitario', 14, 4)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('igv', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();

            $table->index('comprobante_id');
            $table->index('compra_item_id');
            $table->index('cotizacion_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_items');
        Schema::dropIfExists('comprobantes');
    }
};
