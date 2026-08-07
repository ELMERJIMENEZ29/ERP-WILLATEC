<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class LicitacionComentario extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'licitacion_comentarios';

    protected $fillable = [
        'licitacion_id',
        'usuario',
        'comentario',
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
        return 'Comentario de licitacion';
    }
}
