<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventarioMovimiento extends Model
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SALIDA = 'salida';

    public const TIPO_RESERVA = 'reserva';

    public const TIPO_LIBERACION_RESERVA = 'liberacion_reserva';

    public const TIPO_DEVOLUCION = 'devolucion';

    public const TIPO_AJUSTE_MANUAL = 'ajuste_manual';

    public const TIPO_SINCRONIZACION_WOOCOMMERCE = 'sincronizacion_woocommerce';

    public const TIPO_REVERSO = 'reverso';

    protected $table = 'inventario_movimientos';

    protected $fillable = [
        'producto_id',
        'producto_serie_id',
        'tipo_movimiento',
        'cantidad',
        'entrada_cantidad',
        'salida_cantidad',
        'stock_antes',
        'stock_despues',
        'saldo_cantidad',
        'costo_unitario',
        'moneda_id',
        'costo_promedio_antes',
        'costo_promedio_despues',
        'valor_movimiento',
        'valor_stock_despues',
        'referencia_tipo',
        'referencia_id',
        'origen',
        'idempotency_key',
        'observacion',
        'documento_tipo',
        'documento_numero',
        'documento_path',
        'fecha_documento',
        'proveedor',
        'proveedor_id',
        'ip_origen',
        'user_agent',
        'created_by',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function productoSerie(): BelongsTo
    {
        return $this->belongsTo(ProductoSerie::class);
    }

    public function productoSeries(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductoSerie::class,
            'inventario_movimiento_producto_serie',
            'inventario_movimiento_id',
            'producto_serie_id'
        )->withTimestamps();
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function proveedorCatalogo(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'entrada_cantidad' => 'decimal:2',
            'salida_cantidad' => 'decimal:2',
            'stock_antes' => 'decimal:2',
            'stock_despues' => 'decimal:2',
            'saldo_cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:4',
            'costo_promedio_antes' => 'decimal:4',
            'costo_promedio_despues' => 'decimal:4',
            'valor_movimiento' => 'decimal:2',
            'valor_stock_despues' => 'decimal:2',
            'fecha_documento' => 'date:Y-m-d',
        ];
    }
}
