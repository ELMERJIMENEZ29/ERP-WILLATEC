<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionCostosAdicional extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'cotizacion_costos_adicionales';

    protected $fillable = [
        'tipo',
        'descripcion',
        'monto',
        'cotizacion_id',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    protected function auditModelName(): string
    {
        return 'Costo adicional de cotizacion';
    }
}
