<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class LicitacionArchivo extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'licitacion_archivos';

    protected $fillable = [
        'licitacion_id',
        'tipo_archivo',
        'nombre',
        'mime_type',
        'tamanio',
        'data_url',
        'path',
        'creado_por',
        'creado_en',
    ];

    protected $casts = [
        'creado_en' => 'datetime',
    ];

    public function licitacion(): BelongsTo
    {
        return $this->belongsTo(Licitacion::class);
    }

    protected function auditModelName(): string
    {
        return 'Archivo de licitacion';
    }
}
