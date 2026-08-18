<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class LicitacionCotizacion extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'licitacion_cotizaciones';

    protected $fillable = [
        'licitacion_id',
        'cotizacion_id',
        'numero',
        'estado',
        'monto',
        'moneda',
        'origen',
        'creado_por_id',
        'creado_por',
        'creado_en',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'creado_en' => 'datetime',
    ];

    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    protected function auditModelName(): string
    {
        return 'Cotizacion vinculada a licitacion';
    }
}
