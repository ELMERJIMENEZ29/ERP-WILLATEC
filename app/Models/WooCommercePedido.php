<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WooCommercePedido extends Model
{
    public const ESTADO_NUEVO = 'nuevo';

    public const ESTADO_LISTO_RESERVA = 'listo_reserva';

    public const ESTADO_SIN_STOCK = 'sin_stock';

    public const ESTADO_REVISAR = 'revisar';

    public const ESTADO_RESERVADO = 'reservado';

    public const ESTADO_ATENDIDO = 'atendido';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'woocommerce_pedidos';

    protected $fillable = [
        'woo_order_id',
        'numero',
        'estado_woo',
        'estado_erp',
        'cliente_nombre',
        'cliente_email',
        'cliente_telefono',
        'moneda',
        'total',
        'fecha_woo',
        'last_synced_at',
        'reservado_at',
        'atendido_at',
        'raw_payload',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WooCommercePedidoItem::class, 'woocommerce_pedido_id');
    }

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'fecha_woo' => 'datetime',
            'last_synced_at' => 'datetime',
            'reservado_at' => 'datetime',
            'atendido_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
