<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class RequerimientoCompra extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'requerimientos_compra';

    public const ORIGEN_OC_CLIENTE = 'oc_cliente';

    public const ORIGEN_REPOSICION_STOCK = 'reposicion_stock';

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_LICITACION = 'licitacion';

    public const ORIGEN_OTRO = 'otro';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_GESTION = 'en_gestion';

    public const ESTADO_PARCIALMENTE_COMPRADO = 'parcialmente_comprado';

    public const ESTADO_COMPRADO = 'comprado';

    public const ESTADO_CANCELADO = 'cancelado';

    public const PRIORIDAD_BAJA = 'baja';

    public const PRIORIDAD_NORMAL = 'normal';

    public const PRIORIDAD_ALTA = 'alta';

    public const PRIORIDAD_URGENTE = 'urgente';

    protected $fillable = [
        'numero',
        'origen_tipo',
        'oc_recibida_id',
        'estado',
        'prioridad',
        'solicitado_por',
        'asignado_a',
        'observacion',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RequerimientoCompraItem::class);
    }

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /**
     * @return array<int, string>
     */
    public static function origenes(): array
    {
        return [
            self::ORIGEN_OC_CLIENTE,
            self::ORIGEN_REPOSICION_STOCK,
            self::ORIGEN_MANUAL,
            self::ORIGEN_LICITACION,
            self::ORIGEN_OTRO,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function estadosActivos(): array
    {
        return [
            self::ESTADO_PENDIENTE,
            self::ESTADO_EN_GESTION,
            self::ESTADO_PARCIALMENTE_COMPRADO,
        ];
    }
}
