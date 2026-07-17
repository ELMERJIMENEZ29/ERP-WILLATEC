<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Proveedor extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'ruc',
        'contacto',
        'telefono',
        'correo',
        'direccion',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
