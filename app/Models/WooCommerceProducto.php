<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommerceProducto extends Model
{
    protected $table = 'woocommerce_productos';

    protected $fillable = [
        'producto_id',
        'woocommerce_store_id',
        'woo_product_id',
        'woo_variation_id',
        'woo_parent_id',
        'woo_sku',
        'manage_stock',
        'last_stock_sent',
        'last_stock_received',
        'last_sync_status',
        'last_sync_error',
        'last_synced_at',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manage_stock' => 'boolean',
            'last_stock_sent' => 'decimal:2',
            'last_stock_received' => 'decimal:2',
            'last_synced_at' => 'datetime',
        ];
    }
}
