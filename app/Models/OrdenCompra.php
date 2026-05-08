<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrdenCompra extends Model
{
    protected $fillable = [
        'numero',
        'fecha',
        'estado',
        'observaciones',
        'fecha_entrega',
        'moneda',
        'subtotal',
        'igv',
        'total',
        'cliente_nombre',
        'cliente_ruc',
        'cliente_contacto',
        'cliente_correo',
        'cotizacion_id',
        'cliente_id',
        'usuario_id'

    ];

    public function items() : HasMany{
        return $this->hasMany(OrdenCompraItem::class);
    }

    public function cotizacion() : BelongsTo {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente() : BelongsTo {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo{
        return $this->belongsTo(User::class);
    }
}
