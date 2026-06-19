<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;

class Cotizacion extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero',
        'fecha',
        'validez_dias',
        'forma_pago',
        'tipo_cambio',
        'titulo',
        'modo_distribucion',
        'moneda_id',

        'subtotal',
        'igv',
        'total',
        'ganancia',
        'total_gasto',

        'cliente_id',
        'plantilla_id',
        'estado_cotizacion_id',
        'user_id',
        'plataforma_id',

        'cliente_nombre',
        'cliente_ruc',
        'cliente_contacto',
        'cliente_telefono',
        'cliente_correo',
        'delegado_id',
        'delegado_cotizacion_id',
    ];

    // Relaciones
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function estadoCotizacion(): BelongsTo
    {
        return $this->belongsTo(EstadoCotizacion::class, 'estado_cotizacion_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function plataforma(): BelongsTo
    {
        return $this->belongsTo(Plataforma::class);
    }

    public function costosAdicionales(): HasMany
    {
        return $this->hasMany(CotizacionCostosAdicional::class, 'cotizacion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delegado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegado_id');
    }

    public function delegadoCotizacion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegado_cotizacion_id');
    }

    public function ordenCompra(): HasOne
    {
        return $this->hasOne(OrdenCompra::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function historial(): HasMany
    {
        return $this->hasMany(CotizacionHistorial::class);
    }

    public function versiones(): HasMany
    {
        return $this->hasMany(CotizacionVersion::class)->orderBy('version_number');
    }

    public function modificaciones(): HasMany
    {
        return $this->hasMany(CotizacionModificacion::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'validez_dias' => 'integer',
            'tipo_cambio' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'ganancia' => 'decimal:2',
            'total_gasto' => 'decimal:2',
        ];
    }

    protected function auditModelName(): string
    {
        return 'Cotizacion';
    }
}
