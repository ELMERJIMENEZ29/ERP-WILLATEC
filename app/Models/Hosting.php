<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Hosting extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'cliente_id',
        'empresa',
        'ruc',
        'dominio',
        'plan',
        'precio_sin_igv',
        'moneda_id',
        'suscripcion',
        'fecha_inicio',
        'fecha_renovacion',
        'contacto',
        'cliente',
        'correo_hosting',
        'renovacion_programada',
        'renovacion_modo',
        'renovacion_meses',
        'renovacion_programada_para',
        'renovacion_programada_at',
        'renovacion_programada_por',
    ];

    protected $casts = [
        'precio_sin_igv' => 'decimal:2',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_renovacion' => 'date:Y-m-d',
        'renovacion_programada' => 'boolean',
        'renovacion_meses' => 'integer',
        'renovacion_programada_para' => 'date:Y-m-d',
        'renovacion_programada_at' => 'datetime',
    ];

    public function clienteRelacionado(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(HostingDocumento::class);
    }

    public function alertasEnviadas(): HasMany
    {
        return $this->hasMany(HostingAlertaEnviada::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    protected function auditModelName(): string
    {
        return 'Hosting';
    }
}
