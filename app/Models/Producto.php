<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $fillable = [
        'nombre',
        'marca',
        'modelo',
        'codigo',
        'descripcion',
        'precio_referencial',
        'unidad_medida',
        'activo',
    ];

    public function cotizacionItems() : HasMany{
        return $this->hasMany(CotizacionItem::class);
    }
}
