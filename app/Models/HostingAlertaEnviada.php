<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingAlertaEnviada extends Model
{
    protected $table = 'hosting_alertas_enviadas';

    protected $fillable = [
        'hosting_id',
        'dias_antes',
        'correo_destino',
        'correo_copia',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function hosting(): BelongsTo
    {
        return $this->belongsTo(Hosting::class);
    }
}
