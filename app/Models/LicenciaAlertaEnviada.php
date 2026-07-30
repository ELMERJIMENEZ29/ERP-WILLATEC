<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaAlertaEnviada extends Model
{
    protected $table = 'licencia_alertas_enviadas';

    protected $fillable = [
        'licencia_id',
        'dias_antes',
        'correo_destino',
        'correo_copia',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class);
    }
}
