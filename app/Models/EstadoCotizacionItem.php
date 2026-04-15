<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoCotizacionItem extends Model
{
    protected $fillable = [
        'nombre'
        
    ];

    public function items() : HasMany
    {
        return $this->hasMany(CotizacionItem::class, 'estado_cotizacion_item_id');
    }
}
