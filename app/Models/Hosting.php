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
    ];

    protected $casts = [
        'precio_sin_igv' => 'decimal:2',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_renovacion' => 'date:Y-m-d',
    ];

    public function clienteRelacionado(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(HostingDocumento::class);
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
