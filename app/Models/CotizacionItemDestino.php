<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionItemDestino extends Model
{
    protected $table = 'cotizacion_item_destinos';

    protected $fillable = [
        'cotizacion_item_id',
        'destino_entrega',
        'detalle_variante',
        'cantidad',
        'margen',
        'costo_unitario',
        'precio_venta',
        'subtotal',
        'costo_total',
        'ganancia',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class, 'cotizacion_item_id');
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
        ];
    }
}
