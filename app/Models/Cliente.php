<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $fillable = [
        'nombre',
        'ruc',
        'telefono',
        'correo',
        'estado',
        'tipo_cliente_id',
        'moneda_id',
    ];

    public function tipoCliente() : BelongsTo
    {
        return $this->belongsTo(TipoCliente::class);
    }

    public function moneda() : BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function cotizaciones() : HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }
}
