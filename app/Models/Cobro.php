<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class Cobro extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const ESTADO_REGISTRADO = 'registrado';

    public const ESTADO_ANULADO = 'anulado';

    protected $fillable = [
        'cuenta_por_cobrar_id',
        'fecha_cobro',
        'monto',
        'moneda_id',
        'metodo_cobro',
        'referencia',
        'estado',
        'idempotency_key',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_cobro' => 'date:Y-m-d',
            'monto' => 'decimal:2',
        ];
    }

    public function cuentaPorCobrar(): BelongsTo
    {
        return $this->belongsTo(CuentaPorCobrar::class);
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
