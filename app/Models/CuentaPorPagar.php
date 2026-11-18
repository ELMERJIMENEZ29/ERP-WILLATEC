<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected $appends = ['fecha_vencimiento_origen', 'dias_retraso'];

    protected $fillable = [
        'comprobante_id',
        'tipo_cuenta',
        'cuenta_origen_id',
        'pago_origen_id',
        'compra_id',
        'proveedor_id',
        'beneficiario',
        'moneda_id',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_estimada_pago',
        'condicion_pago',
        'total',
        'monto_pagado',
        'saldo',
        'comision_origen',
        'numero_cuotas',
        'estado',
        'estado_operativo',
        'detraccion_monto_original',
        'detraccion_monto_programado',
        'detraccion_fecha_programada',
        'detraccion_fecha_pago',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'fecha_estimada_pago' => 'date:Y-m-d',
            'detraccion_fecha_programada' => 'date:Y-m-d',
            'detraccion_fecha_pago' => 'date:Y-m-d',
            'total' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'saldo' => 'decimal:2',
            'comision_origen' => 'decimal:2',
            'numero_cuotas' => 'integer',
            'detraccion_monto_original' => 'decimal:2',
            'detraccion_monto_programado' => 'decimal:2',
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

    public function cuentaOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'cuenta_origen_id');
    }

    public function devoluciones(): HasMany
    {
        return $this->hasMany(self::class, 'cuenta_origen_id');
    }

    public function pagoOrigen(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_origen_id');
    }

    public function cheques(): BelongsToMany
    {
        return $this->belongsToMany(Cheque::class, 'cheque_aplicaciones', 'cuenta_por_pagar_id', 'cheque_id')
            ->withPivot('monto_aplicado')
            ->withTimestamps();
    }

    public function prorrogas(): HasMany
    {
        return $this->hasMany(CuentaPorPagarProrroga::class);
    }

    public function getFechaVencimientoOrigenAttribute(): string
    {
        if ($this->comprobante?->fecha_vencimiento) {
            return 'comprobante';
        }

        if ($this->proveedor?->tiene_credito) {
            return 'credito_proveedor';
        }

        return $this->fecha_vencimiento ? 'contado' : 'sin_fecha';
    }

    public function getDiasRetrasoAttribute(): int
    {
        if ((float) $this->saldo <= 0 || $this->estado === self::ESTADO_ANULADA) {
            return 0;
        }
        $fecha = $this->fecha_estimada_pago ?: $this->fecha_vencimiento ?: now();

        return $fecha->isPast() ? $fecha->diffInDays(now()->startOfDay()) : 0;
    }
}
