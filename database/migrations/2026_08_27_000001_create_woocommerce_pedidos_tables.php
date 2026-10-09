<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('woocommerce_pedidos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('woo_order_id')->unique();
            $table->string('numero', 80)->nullable();
            $table->string('estado_woo', 40)->nullable();
            $table->string('estado_erp', 40)->default('nuevo');
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('cliente_telefono', 60)->nullable();
            $table->string('moneda', 10)->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('fecha_woo')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('reservado_at')->nullable();
            $table->timestamp('atendido_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['estado_erp', 'estado_woo']);
            $table->index('fecha_woo');
            $table->index('last_synced_at');
        });

        Schema::create('woocommerce_pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('woocommerce_pedido_id')
                ->constrained('woocommerce_pedidos')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('woo_line_item_id')->nullable();
            $table->unsignedBigInteger('woo_product_id')->nullable();
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('sku', 120)->nullable();
            $table->string('nombre')->nullable();
            $table->decimal('cantidad', 12, 2)->default(0);
            $table->decimal('precio_unitario', 14, 4)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('stock_disponible_snapshot', 12, 2)->nullable();
            $table->string('estado_match', 40)->default('pendiente');
            $table->string('estado_reserva', 40)->default('pendiente');
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->unique(['woocommerce_pedido_id', 'woo_line_item_id'], 'woo_pedido_item_line_unique');
            $table->index(['producto_id', 'estado_reserva']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_pedido_items');
        Schema::dropIfExists('woocommerce_pedidos');
    }
};
