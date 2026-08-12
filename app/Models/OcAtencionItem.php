<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;

class OcAtencionItem extends Model
{
    use Auditable, LogsActivity;

    protected $table = 'oc_atencion_items';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_CANCELADO = 'cancelado';

    protected $fillable = [
        'oc_atencion_id',
        'oc_recibida_item_id',
        'producto_id',
        'descripcion',
        'codigo',
        'marca',
        'unidad_medida',
        'cantidad',
        'cantidad_entregada',
        'inventario_movimiento_id',
        'estado',
    ];

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(OcAtencion::class, 'oc_atencion_id');
    }

    public function ocRecibidaItem(): BelongsTo
    {
        return $this->belongsTo(OcRecibidaItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function inventarioMovimiento(): BelongsTo
    {
        return $this->belongsTo(InventarioMovimiento::class);
    }

    public function series(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductoSerie::class,
            'oc_atencion_item_producto_serie',
            'oc_atencion_item_id',
            'producto_serie_id'
        )->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'cantidad_entregada' => 'decimal:2',
        ];
    }
}
