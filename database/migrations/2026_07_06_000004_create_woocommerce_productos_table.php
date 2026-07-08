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
        Schema::create('woocommerce_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->unsignedBigInteger('woocommerce_store_id')->nullable();
            $table->unsignedBigInteger('woo_product_id');
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->unsignedBigInteger('woo_parent_id')->nullable();
            $table->string('woo_sku');
            $table->boolean('manage_stock')->default(true);
            $table->decimal('last_stock_sent', 12, 2)->nullable();
            $table->decimal('last_stock_received', 12, 2)->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'woocommerce_store_id'], 'woo_productos_producto_store_unique');
            $table->unique(['woo_product_id', 'woo_variation_id'], 'woo_productos_woo_ids_unique');
            $table->index('woo_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woocommerce_productos');
    }
};
