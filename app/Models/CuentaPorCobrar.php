<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class CuentaPorCobrar extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_COBRADA = 'cobrada';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADO_ANULADA = 'anulada';

    protected $table = 'cuentas_por_cobrar';

    protected $appends = ['dias_retraso'];

    protected $fillable = [
        'comprobante_id',
        'oc_recibida_id',
        'cotizacion_id',
        'cliente_id',
        'moneda_id',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_estimada_pago',
        'total',
        'monto_cobrado',
        'saldo',
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
            'monto_cobrado' => 'decimal:2',
            'saldo' => 'decimal:2',
            'detraccion_monto_original' => 'decimal:2',
            'detraccion_monto_programado' => 'decimal:2',
        ];
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class);
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
