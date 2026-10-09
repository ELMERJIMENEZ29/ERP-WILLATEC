<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenciaCotizacion extends Model
{
    protected $table = 'licencia_cotizaciones';

    protected $fillable = [
        'licencia_id',
        'cotizacion_id',
        'created_by',
    ];

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
