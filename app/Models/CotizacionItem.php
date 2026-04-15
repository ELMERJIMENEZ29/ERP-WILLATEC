<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionItem extends Model
{
    protected $fillable = [
        'descripcion',
        'cantidad',
        'marca',
        'codigo',
        'unidad_medida',
        'disponibilidad',
        'costo_unitario',
        'costo_base',
        'margen',
        'precio_venta',
        'subtotal',
        'imagen',
        'orden',
        'cotizacion_id',
        'producto_id',
        'estado_cotizacion_item_id',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function producto() : BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function estadoCotizacionItem() : BelongsTo
    {
        return $this->belongsTo(EstadoCotizacionItem::class, 'estado_cotizacion_item_id');
    }

}
