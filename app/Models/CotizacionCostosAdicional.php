<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionCostosAdicional extends Model
{
    protected $fillable= [
        'tipo',
        'descripcion',
        'monto',
        'cotizacion_id',
    ];

    public function cotizacion() : BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }
}
