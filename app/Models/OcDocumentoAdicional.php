<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;

class OcDocumentoAdicional extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'oc_documentos_adicionales';

    protected $fillable = [
        'oc_recibida_id',
        'oc_emitida_id',
        'nombre_original',
        'path',
        'mime_type',
        'size',
        'created_by',
    ];

    protected $appends = [
        'url',
    ];

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function ocEmitida(): BelongsTo
    {
        return $this->belongsTo(OcEmitida::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
