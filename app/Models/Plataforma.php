<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Plataforma extends Model
{
    protected $fillable = [
        'nombre'
        ];

    //RELACIONES

    public function cotizacion() : HasOne{
        return $this->hasOne(Cotizacion::class);
    }
}
