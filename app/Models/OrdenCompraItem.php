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
        'costo_unitario',
        'costo_total',
        'estado',
    ];

    public function orden() : BelongsTo{
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }
}
