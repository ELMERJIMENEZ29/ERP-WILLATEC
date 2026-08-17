<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class CuentaPorPagar extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_PAGADA = 'pagada';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'cuentas_por_pagar';

    protected $fillable = [
        'comprobante_id',
        'compra_id',
        'proveedor_id',
        'moneda_id',
        'fecha_emision',
        'fecha_vencimiento',
        'total',
        'monto_pagado',
        'saldo',
        'estado',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'saldo' => 'decimal:2',
        ];
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
