<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OcRecibida extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_POR_ENTREGA = 'por_entrega';

    public const ESTADO_ATENDIDO = 'atendido';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'numero',
        'fecha_recepcion',
        'estado',
        'observaciones',
        'orden_compra_cliente_path',
        'guia_emision_path',
        'factura_numero',
        'factura_path',
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
        return $this->hasMany(OcRecibidaItem::class);
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
            'orden_compra_cliente' => $this->orden_compra_cliente_path,
            'guia_emision' => $this->guia_emision_path,
            'factura_numero' => $this->factura_numero,
            'factura' => $this->factura_path,
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
            'fecha_recepcion' => 'date:Y-m-d',
        ];
    }
}
