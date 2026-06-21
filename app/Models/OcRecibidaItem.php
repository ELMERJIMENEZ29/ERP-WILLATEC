<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OcRecibidaItem extends Model
{
    protected $fillable = [
        'oc_recibida_id',
        'cotizacion_item_id',
        'descripcion',
        'codigo',
        'marca',
        'unidad_medida',
        'cantidad_cotizada',
        'cantidad_recibida',
        'seleccionado',
        'comprado',
        'entregado',
    ];

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
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
            'cantidad_cotizada' => 'integer',
            'cantidad_recibida' => 'integer',
            'seleccionado' => 'boolean',
            'comprado' => 'boolean',
            'entregado' => 'boolean',
        ];
    }
}
