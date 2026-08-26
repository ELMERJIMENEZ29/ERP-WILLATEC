<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Licencia extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'cliente_id',
        'empresa',
        'producto',
        'cantidad',
        'precio_sin_igv',
        'moneda_id',
        'suscripcion_meses',
        'correo_licencia',
        'fecha_inicio',
        'fecha_renovacion',
        'renovacion_programada',
        'renovacion_modo',
        'renovacion_meses',
        'renovacion_programada_para',
        'renovacion_programada_at',
        'renovacion_programada_por',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_sin_igv' => 'decimal:2',
        'suscripcion_meses' => 'integer',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_renovacion' => 'date:Y-m-d',
        'renovacion_programada' => 'boolean',
        'renovacion_meses' => 'integer',
        'renovacion_programada_para' => 'date:Y-m-d',
        'renovacion_programada_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function alertasEnviadas(): HasMany
    {
        return $this->hasMany(LicenciaAlertaEnviada::class);
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(LicenciaDocumento::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    protected function auditModelName(): string
    {
        return 'Licencia';
    }
}
