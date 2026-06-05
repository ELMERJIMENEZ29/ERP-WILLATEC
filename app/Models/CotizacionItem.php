<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionItem extends Model
{
    use Auditable, LogsActivity;

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
        'tipo',
        'estado_cotizacion_item_id',
        'costo_total',
        'ganancia',
        'garantia_meses',
        'disponibilidad_tipo',
        'disponibilidad_dias',
        'proveedor',
        'link_proveedor',
        'stock',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function estadoCotizacionItem(): BelongsTo
    {
        return $this->belongsTo(EstadoCotizacionItem::class, 'estado_cotizacion_item_id');
    }

    protected function auditModelName(): string
    {
        return 'Item de cotizacion';
    }
}
