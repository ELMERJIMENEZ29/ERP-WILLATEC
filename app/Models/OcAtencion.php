<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class OcAtencion extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'oc_atenciones';

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_PREPARANDO = 'preparando';

    public const ESTADO_DESPACHADO = 'despachado';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'oc_recibida_id',
        'numero',
        'fecha_atencion',
        'estado',
        'tipo_atencion',
        'observacion',
        'preparado_por',
        'entregado_por',
        'fecha_entrega',
        'created_by',
    ];

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OcAtencionItem::class);
    }

    public function preparadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparado_por');
    }

    public function entregadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entregado_por');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_atencion' => 'datetime',
            'fecha_entrega' => 'datetime',
        ];
    }
}
