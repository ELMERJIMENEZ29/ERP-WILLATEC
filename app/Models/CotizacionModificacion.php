<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionModificacion extends Model
{
    use Auditable, LogsActivity;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_EN_REVISION = 'en_revision';

    public const ESTADO_APROBADA = 'aprobada';

    public const ESTADO_RECHAZADA = 'rechazada';

    protected $table = 'cotizacion_modificaciones';

    protected $fillable = [
        'cotizacion_id',
        'original_version_id',
        'version_number',
        'estado',
        'motivo',
        'propuesta',
        'requested_by',
        'reviewed_by',
        'comentario_revision',
        'comentario_reenvio_revision',
        'submitted_at',
        'reviewed_at',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function originalVersion(): BelongsTo
    {
        return $this->belongsTo(CotizacionVersion::class, 'original_version_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'propuesta' => 'array',
            'version_number' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function auditModelName(): string
    {
        return 'Modificacion de cotizacion';
    }
}
