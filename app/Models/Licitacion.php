<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class Licitacion extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'licitaciones';

    protected $fillable = [
        'tipo',
        'empresa',
        'requerimiento',
        'vigencia',
        'categoria',
        'estado',
        'observacion',
        'ejecutivo_id',
        'ejecutivo_nombre',
        'ejecutivo_email',
        'asignado_a',
        'asignado_en',
        'asignado_por',
        'es_nueva',
        'created_by',
        'creado_por',
        'modificado_por',
        'creado_en',
        'modificado_en',
        'garantia',
        'plazo',
        'carpeta_servidor',
        'forma_pago',
        'destino_entrega',
        'wherex_id',
        'wherex_url',
        'comentarios_generales',
        'motivo_cierre',
        'comentario_cierre',
        'perdida_info',
        'lecciones_aprendidas',
    ];

    protected $casts = [
        'vigencia' => 'datetime',
        'asignado_en' => 'datetime',
        'creado_en' => 'datetime',
        'modificado_en' => 'datetime',
        'es_nueva' => 'boolean',
        'perdida_info' => 'array',
        'lecciones_aprendidas' => 'array',
    ];

    public function ejecutivo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ejecutivo_id');
    }

    public function asignadoUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(LicitacionArchivo::class);
    }

    public function comentarios(): HasMany
    {
        return $this->hasMany(LicitacionComentario::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(LicitacionHistorial::class);
    }

    public function cotizaciones(): HasMany
    {
        return $this->hasMany(LicitacionCotizacion::class);
    }

    public function vistas(): HasMany
    {
        return $this->hasMany(LicitacionVista::class);
    }

    protected function auditModelName(): string
    {
        return 'Licitacion';
    }
}
