<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;

class Comprobante extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const TIPO_OPERACION_COMPRA = 'compra';

    public const TIPO_OPERACION_VENTA = 'venta';

    public const TIPO_OPERACION_GASTO = 'gasto';

    public const ESTADO_REGISTRADO = 'registrado';

    public const ESTADO_ANULADO = 'anulado';

    protected $fillable = [
        'tipo_operacion',
        'categoria_gasto',
        'centro_costo',
        'compra_id',
        'oc_recibida_id',
        'cotizacion_id',
        'cliente_id',
        'proveedor_id',
        'emisor_ruc',
        'emisor_nombre',
        'receptor_ruc',
        'receptor_nombre',
        'tipo_comprobante',
        'serie',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'fecha_vencimiento_origen',
        'condicion_pago',
        'moneda_id',
        'subtotal',
        'igv',
        'total',
        'detraccion_porcentaje',
        'detraccion_monto_original',
        'detraccion_codigo',
        'detraccion_cuenta',
        'estado',
        'xml_hash',
        'archivo_path',
        'xml_path',
        'observacion',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'detraccion_porcentaje' => 'decimal:4',
            'detraccion_monto_original' => 'decimal:2',
        ];
    }

    protected $appends = ['archivo_url'];

    public function getArchivoUrlAttribute(): ?string
    {
        return $this->archivo_path ? Storage::disk('public')->url($this->archivo_path) : null;
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function ocRecibida(): BelongsTo
    {
        return $this->belongsTo(OcRecibida::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class);
    }

    public function cuentaPorPagar(): HasOne
    {
        return $this->hasOne(CuentaPorPagar::class);
    }

    public function cuentaPorCobrar(): HasOne
    {
        return $this->hasOne(CuentaPorCobrar::class);
    }

    public function notasCredito(): HasMany
    {
        return $this->hasMany(NotaCredito::class);
    }
}
