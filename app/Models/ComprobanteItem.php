<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;

class ComprobanteItem extends Model
{
    use Auditable, HasFactory, LogsActivity;

    protected $fillable = [
        'comprobante_id',
        'compra_item_id',
        'cotizacion_item_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'valor_unitario',
        'subtotal',
        'igv',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'valor_unitario' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function compraItem(): BelongsTo
    {
        return $this->belongsTo(CompraItem::class);
    }

    public function cotizacionItem(): BelongsTo
    {
        return $this->belongsTo(CotizacionItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
