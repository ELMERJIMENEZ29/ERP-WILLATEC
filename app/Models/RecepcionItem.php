<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Traits\LogsActivity;

class RecepcionItem extends Model
{
    use Auditable, LogsActivity;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CONFIRMADO = 'confirmado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $table = 'recepcion_items';

    protected $fillable = [
        'recepcion_compra_id',
        'compra_item_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'costo_unitario_provisional',
        'moneda_id',
        'estado',
        'inventario_movimiento_id',
    ];

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionCompra::class, 'recepcion_compra_id');
    }

    public function compraItem(): BelongsTo
    {
        return $this->belongsTo(CompraItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function inventarioMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventarioMovimiento::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(ProductoSerie::class);
    }

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario_provisional' => 'decimal:4',
        ];
    }
}
