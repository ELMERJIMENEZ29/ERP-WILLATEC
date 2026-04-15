<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Moneda extends Model
{
    protected $fillable = [
        'codigo',
        'simbolo',
    ];

    public function plantillas() : HasMany
    {
        return $this->hasMany(Plantilla::class);
    }
}
