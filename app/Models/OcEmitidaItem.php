<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class OcEmitidaItem extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'oc_emitida_id',
        'cotizacion_item_id',
        'descripcion',
        'codigo',
        'marca',
        'unidad_medida',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'estado',
    ];

    public function ocEmitida(): BelongsTo
    {
        return $this->belongsTo(OcEmitida::class);
    }

    public function cotizacionItem(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
