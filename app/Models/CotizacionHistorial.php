<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionHistorial extends Model
{
    use Auditable, LogsActivity;

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
