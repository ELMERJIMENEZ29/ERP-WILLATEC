<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooCommercePedidoItem extends Model
{
    public const MATCH_PENDIENTE = 'pendiente';

    public const MATCH_OK = 'ok';

    public const MATCH_SIN_SKU = 'sin_sku';

    public const MATCH_NO_ENCONTRADO = 'no_encontrado';

    public const MATCH_SIN_STOCK = 'sin_stock';

    public const MATCH_STOCK_PARCIAL = 'stock_parcial';

    public const RESERVA_PENDIENTE = 'pendiente';

    public const RESERVA_RESERVADO = 'reservado';

    public const RESERVA_ATENDIDO = 'atendido';

    public const RESERVA_CANCELADO = 'cancelado';

    protected $table = 'woocommerce_pedido_items';

    protected $fillable = [
        'woocommerce_pedido_id',
        'woo_line_item_id',
        'woo_product_id',
        'woo_variation_id',
        'producto_id',
        'sku',
        'nombre',
        'cantidad',
        'precio_unitario',
        'total',
        'stock_disponible_snapshot',
        'estado_match',
        'estado_reserva',
        'mensaje',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(WooCommercePedido::class, 'woocommerce_pedido_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:4',
            'total' => 'decimal:2',
            'stock_disponible_snapshot' => 'decimal:2',
        ];
    }
}
