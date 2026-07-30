<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpresaConfiguracion extends Model
{
    protected $table = 'empresa_configuraciones';

    protected $fillable = [
        'nombre',
        'ruc',
        'direccion',
        'telefono',
        'correo',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
