<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Licencia extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'cliente_id',
        'empresa',
        'producto',
        'cantidad',
        'suscripcion_meses',
        'correo_licencia',
        'fecha_inicio',
        'fecha_renovacion',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'suscripcion_meses' => 'integer',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_renovacion' => 'date:Y-m-d',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function alertasEnviadas(): HasMany
    {
        return $this->hasMany(LicenciaAlertaEnviada::class);
    }

    protected function auditModelName(): string
    {
        return 'Licencia';
    }
}
