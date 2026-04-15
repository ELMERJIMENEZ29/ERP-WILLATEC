<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCliente extends Model
{
    protected $fillable = [
        'nombre',
    ];

    public function clientes() : HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
