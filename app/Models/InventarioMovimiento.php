<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_RESERVA = 'reserva';

    public const TIPO_LIBERACION_RESERVA = 'liberacion_reserva';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_AJUSTE_MANUAL = 'ajuste_manual';

    public const TIPO_SINCRONIZACION_WOOCOMMERCE = 'sincronizacion_woocommerce';

    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'tipo_movimiento',
        'cantidad',
        'stock_antes',
        'stock_despues',
        'referencia_tipo',
        'referencia_id',
        'origen',
        'idempotency_key',
        'observacion',
        'ip_origen',
        'user_agent',
        'created_by',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'stock_antes' => 'decimal:2',
            'stock_despues' => 'decimal:2',
        ];
    }
}
