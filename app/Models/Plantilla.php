<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plantilla extends Model
{
    protected $fillable = [
        'nombre',
        'incluye_igv',
        'formato_pdf',
        'moneda_id',
        'activo',
    ];

    public function moneda() : BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }
}
