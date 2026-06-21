<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OcEmitida extends Model
{
    public const ESTADO_EMITIDA = 'emitida';

    protected $fillable = [
        'numero',
        'fecha_emision',
        'estado',
        'proveedor',
        'observaciones',
        'factura_path',
        'comprobante_pago_path',
        'pdf_path',
        'moneda',
        'subtotal',
        'igv',
        'total',
        'cliente_nombre',
        'cliente_ruc',
        'cliente_contacto',
        'cliente_correo',
        'cotizacion_id',
        'cliente_id',
        'user_id',
    ];

    protected $appends = [
        'documentos_completos',
        'documentos_faltantes',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OcEmitidaItem::class);
    }

    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<int, string>
     */
    public function documentosFaltantes(): array
    {
        return collect([
            'factura' => $this->factura_path,
            'comprobante_pago' => $this->comprobante_pago_path,
        ])->filter(fn (?string $path): bool => blank($path))->keys()->values()->all();
    }

    public function getDocumentosCompletosAttribute(): bool
    {
        return $this->documentosFaltantes() === [];
    }

    /**
     * @return array<int, string>
     */
    public function getDocumentosFaltantesAttribute(): array
    {
        return $this->documentosFaltantes();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }
}
