<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'imagen',
        'activo',
        'stock',
        'categoria_id',
    ];

    public function cotizacionItems(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }
}
