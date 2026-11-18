<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class Pago extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const ESTADO_REGISTRADO = 'registrado';

    public const ESTADO_ANULADO = 'anulado';

    protected $fillable = [
        'cuenta_por_pagar_id',
        'fecha_pago',
        'monto',
        'moneda_id',
        'tipo_cambio',
        'monto_moneda_cuenta',
        'monto_desembolsado',
        'numero_cuotas',
        'pagado_por',
        'metodo_pago',
        'referencia',
        'estado',
        'idempotency_key',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'tipo_cambio' => 'decimal:6',
            'monto_moneda_cuenta' => 'decimal:2',
            'monto_desembolsado' => 'decimal:2',
            'numero_cuotas' => 'integer',
        ];
    }

    public function cuentaPorPagar(): BelongsTo
    {
        return $this->belongsTo(CuentaPorPagar::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
