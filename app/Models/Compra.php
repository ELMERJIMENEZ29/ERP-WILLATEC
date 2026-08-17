<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Compra extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const MODALIDAD_DIRECTA = 'directa';

    public const MODALIDAD_OC_PROVEEDOR = 'oc_proveedor';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_PARCIALMENTE_RECIBIDA = 'parcialmente_recibida';

    public const ESTADO_RECIBIDA = 'recibida';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'numero',
        'proveedor_id',
        'oc_emitida_id',
        'modalidad',
        'estado',
        'fecha_compra',
        'moneda_id',
        'subtotal_estimado',
        'total_estimado',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'subtotal_estimado' => 'decimal:2',
            'total_estimado' => 'decimal:2',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function ocEmitida(): BelongsTo
    {
        return $this->belongsTo(OcEmitida::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CompraItem::class);
    }

    public function recepciones(): HasMany
    {
        return $this->hasMany(RecepcionCompra::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function cuentasPorPagar(): HasMany
    {
        return $this->hasMany(CuentaPorPagar::class);
    }
}
