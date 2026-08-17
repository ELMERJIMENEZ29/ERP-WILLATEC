<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Cliente extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'nombre',
        'ruc',
        'telefono',
        'correo',
        'estado',
        'tipo_cliente_id',
        'moneda_id',
    ];

    public function tipoCliente(): BelongsTo
    {
        return $this->belongsTo(TipoCliente::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function cuentasPorCobrar(): HasMany
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    protected function auditModelName(): string
    {
        return 'Cliente';
    }
}
