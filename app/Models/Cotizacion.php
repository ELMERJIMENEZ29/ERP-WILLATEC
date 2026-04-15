<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cotizacion extends Model
{
    protected $fillable = [
        'fecha',
        'validez_dias',
        'tipo_cambio',
        'titulo',
        'modo_distribucion',

        'subtotal',
        'igv',
        'total',

        'cliente_id',
        'plantilla_id',
        'estado_cotizacion_id',

        'cliente_nombre',
        'cliente_ruc',
        'cliente_contacto',
        'cliente_telefono',
        'cliente_correo'        
    ];

        // Relaciones
        public function cliente() : BelongsTo
        {
            return $this->belongsTo(Cliente::class);
        }
    
        public function plantilla() : BelongsTo
        {
            return $this->belongsTo(Plantilla::class);
        }
    
        public function estadoCotizacion() : BelongsTo
        {
            return $this->belongsTo(EstadoCotizacion::class, 'estado_cotizacion_id');
        }
    
        public function items() : HasMany
        {
            return $this->hasMany(CotizacionItem::class);
        }

        public function plataforma() : BelongsTo
        {
            return $this->belongsTo(Plataforma::class);
        }

        public function costosAdicionales() : HasMany
        {
            return $this->hasMany(CotizacionCostosAdicional::class);
        }
}
