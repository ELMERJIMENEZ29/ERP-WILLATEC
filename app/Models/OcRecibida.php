<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class OcRecibida extends Model
{
    use Auditable, LogsActivity;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_EN_PROCESO = 'en_proceso';

    public const ESTADO_POR_ENTREGA = 'por_entrega';

    public const ESTADO_ATENDIDO = 'atendido';

    public const ESTADO_CANCELADO = 'cancelado';

    public const ESTADO_COMERCIAL_REGISTRADA = 'registrada';

    public const ESTADO_COMERCIAL_EN_ATENCION = 'en_atencion';

    public const ESTADO_COMERCIAL_CERRADA = 'cerrada';

    public const ESTADO_COMERCIAL_CANCELADA = 'cancelada';

    public const ESTADO_LOGISTICO_PENDIENTE = 'pendiente';

    public const ESTADO_LOGISTICO_PREPARANDO = 'preparando';

    public const ESTADO_LOGISTICO_PARCIAL = 'parcial';

    public const ESTADO_LOGISTICO_ENTREGADO = 'entregado';

    public const ESTADO_DOCUMENTAL_PENDIENTE = 'pendiente';

    public const ESTADO_DOCUMENTAL_INCOMPLETO = 'incompleto';

    public const ESTADO_DOCUMENTAL_COMPLETO = 'completo';

    public const ESTADO_FINANCIERO_PENDIENTE = 'pendiente';

    protected $fillable = [
        'numero',
        'fecha_recepcion',
        'estado',
        'estado_comercial',
        'estado_logistico',
        'estado_documental',
        'estado_financiero',
        'observaciones',
        'orden_compra_cliente_path',
        'orden_compra_cliente_nombre_original',
        'orden_compra_cliente_uploaded_by',
        'guia_emision_path',
        'guia_emision_nombre_original',
        'guia_emision_uploaded_by',
        'factura_numero',
        'factura_path',
        'factura_nombre_original',
        'factura_uploaded_by',
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

    public function atenciones(): HasMany
    {
        return $this->hasMany(OcAtencion::class);
    }

    public function requerimientosCompra(): HasMany
    {
        return $this->hasMany(RequerimientoCompra::class);
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    public function cuentasPorCobrar(): HasMany
    {
        return $this->hasMany(CuentaPorCobrar::class);
    }

    public function documentosAdicionales(): HasMany
    {
        return $this->hasMany(OcDocumentoAdicional::class);
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
