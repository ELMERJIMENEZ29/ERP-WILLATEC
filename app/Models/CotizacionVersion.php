<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class CotizacionVersion extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'cotizacion_versiones';

    protected $fillable = [
        'cotizacion_id',
        'version_number',
        'numero_version',
        'snapshot',
        'created_by',
        'approved_by',
        'approved_at',
        'notas',
    ];

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'approved_at' => 'datetime',
            'version_number' => 'integer',
        ];
    }

    protected function auditModelName(): string
    {
        return 'Version de cotizacion';
    }
}
