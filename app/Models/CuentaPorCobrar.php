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

    protected $fillable = [
        'comprobante_id',
        'oc_recibida_id',
        'cotizacion_id',
        'cliente_id',
        'moneda_id',
        'fecha_emision',
        'fecha_vencimiento',
        'total',
        'monto_cobrado',
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
            'monto_cobrado' => 'decimal:2',
            'saldo' => 'decimal:2',
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
}
