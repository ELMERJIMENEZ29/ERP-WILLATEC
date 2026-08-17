<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class CompraItem extends Model
{
    use Auditable, HasFactory, LogsActivity;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PARCIALMENTE_RECIBIDO = 'parcialmente_recibido';

    public const ESTADO_RECIBIDO = 'recibido';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'compra_id',
        'requerimiento_compra_item_id',
        'oc_emitida_item_id',
        'producto_id',
        'producto_externo_id',
        'descripcion',
        'cantidad',
        'cantidad_recibida',
        'costo_unitario_estimado',
        'moneda_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'cantidad_recibida' => 'decimal:2',
            'costo_unitario_estimado' => 'decimal:4',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function requerimientoCompraItem(): BelongsTo
    {
        return $this->belongsTo(RequerimientoCompraItem::class, 'requerimiento_compra_item_id');
    }

    public function ocEmitidaItem(): BelongsTo
    {
        return $this->belongsTo(OcEmitidaItem::class, 'oc_emitida_item_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function productoExterno(): BelongsTo
    {
        return $this->belongsTo(ProductoExterno::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function recepcionItems(): HasMany
    {
        return $this->hasMany(RecepcionItem::class);
    }

    public function comprobanteItems(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class);
    }
}
