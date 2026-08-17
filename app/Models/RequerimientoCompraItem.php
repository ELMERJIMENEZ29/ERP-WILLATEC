<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class RequerimientoCompraItem extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'requerimiento_compra_items';

    protected $fillable = [
        'requerimiento_compra_id',
        'oc_recibida_item_id',
        'cotizacion_item_id',
        'producto_id',
        'producto_externo_id',
        'descripcion',
        'cantidad_requerida',
        'cantidad_comprada',
        'cantidad_recibida',
        'estado',
    ];

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(RequerimientoCompra::class, 'requerimiento_compra_id');
    }

    public function ocRecibidaItem(): BelongsTo
    {
        return $this->belongsTo(OcRecibidaItem::class);
    }

    public function cotizacionItem(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function productoExterno(): BelongsTo
    {
        return $this->belongsTo(ProductoExterno::class);
    }

    public function compraItems(): HasMany
    {
        return $this->hasMany(CompraItem::class, 'requerimiento_compra_item_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_requerida' => 'decimal:2',
            'cantidad_comprada' => 'decimal:2',
            'cantidad_recibida' => 'decimal:2',
        ];
    }
}
