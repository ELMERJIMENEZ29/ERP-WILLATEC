<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;

class LicenciaDocumento extends Model
{
    use Auditable, LogsActivity;

    protected $fillable = [
        'licencia_id',
        'nombre_original',
        'path',
        'mime_type',
        'size',
        'created_by',
    ];

    protected $appends = [
        'url',
    ];

    public function licencia(): BelongsTo
    {
        return $this->belongsTo(Licencia::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }

    protected function auditModelName(): string
    {
        return 'Documento de licencia';
    }
}
