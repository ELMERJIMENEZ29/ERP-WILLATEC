<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraItem extends Model
{
    protected $fillable = [
        'orden_compra_id',
        'cotizacion_item_id',
        'descripcion',
        'codigo',
        'marca',
        'unidad_medida',
        'cantidad',
        'cantidad_aprobada',
        'costo_unitario',
        'costo_total',
        'precio_venta_unitario',
        'subtotal',
        'estado',
    ];

    public function ordenCompra() : BelongsTo{
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function cotizacionItem() : BelongsTo{
        return $this->belongsTo(CotizacionItem::class);
    }
}
