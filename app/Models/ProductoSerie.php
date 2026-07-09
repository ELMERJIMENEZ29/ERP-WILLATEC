<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoSerie extends Model
{
    public const ESTADO_DISPONIBLE = 'disponible';

    public const ESTADO_RESERVADO = 'reservado';

    public const ESTADO_VENDIDO = 'vendido';

    public const ESTADO_DEVUELTO = 'devuelto';

    protected $table = 'producto_series';

    protected $fillable = [
        'producto_id',
        'serie',
        'factura_numero',
        'documento_path',
        'proveedor_id',
        'costo_unitario',
        'moneda_id',
        'fecha_ingreso',
        'estado',
        'oc_recibida_id',
        'cotizacion_item_id',
        'fecha_salida',
        'created_by',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function cotizacionItem(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class);
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
            'costo_unitario' => 'decimal:4',
            'fecha_ingreso' => 'date:Y-m-d',
            'fecha_salida' => 'date:Y-m-d',
        ];
    }
}
