<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class LicitacionHistorial extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'licitacion_historial';

    protected $fillable = [
        'licitacion_id',
        'usuario',
        'tipo',
        'descripcion',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }

    protected function auditModelName(): string
    {
        return 'Historial de licitacion';
    }
}
