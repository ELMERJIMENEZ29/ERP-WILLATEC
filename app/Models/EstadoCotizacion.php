<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoCotizacion extends Model
{
    protected $fillable = [
        'nombre',
    ];

    public function cotizaciones() : HasMany
    {
        return $this->hasMany(Cotizacion::class);
    }
}
