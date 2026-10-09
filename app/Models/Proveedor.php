<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'tiene_credito',
        'dias_credito',
        'limite_credito',
        'moneda_credito_id',
    ];

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function cuentasPorPagar(): HasMany
    {
        return $this->hasMany(CuentaPorPagar::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'tiene_credito' => 'boolean',
            'dias_credito' => 'integer',
            'limite_credito' => 'decimal:2',
        ];
    }
}
