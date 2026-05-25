<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CotizacionHistorial extends Model
{
    protected $table = 'cotizacion_historial';

    protected $fillable = [
        'cotizacion_id',
        'estado_anterior_id',
        'estado_nuevo_id',
        'comentario',
        'user_id',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_anterior_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_nuevo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
