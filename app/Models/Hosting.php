<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class Hosting extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'cliente_id',
        'empresa',
        'ruc',
        'dominio',
        'plan',
        'suscripcion',
        'fecha_inicio',
        'fecha_renovacion',
        'contacto',
        'cliente',
        'correo_hosting',
    ];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_renovacion' => 'date:Y-m-d',
    ];

    public function clienteRelacionado(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    protected function auditModelName(): string
    {
        return 'Hosting';
    }
}
