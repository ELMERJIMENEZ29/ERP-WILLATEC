<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Traits\LogsActivity;

class Producto extends Model
{
    use Auditable, LogsActivity;

    protected array $auditOnly = [
        'nombre',
        'sku',
        'marca',
        'modelo',
        'codigo',
        'codigo_barras',
        'descripcion',
        'tipo_producto',
        'controla_stock',
        'stock_minimo',
        'costo_unitario',
        'costo_promedio',
        'precio_venta',
        'moneda_id',
        'precio_referencial',
        'unidad_medida',
        'imagen',
        'activo',
        'estado',
        'categoria_id',
    ];

    protected $fillable = [
        'nombre',
        'sku',
        'marca',
        'modelo',
        'codigo',
        'codigo_barras',
        'serie',
        'factura_numero',
        'descripcion',
        'tipo_producto',
        'controla_stock',
        'stock_actual',
        'stock_reservado',
        'stock_disponible',
        'stock_minimo',
        'costo_unitario',
        'costo_promedio',
        'valor_stock',
        'precio_venta',
        'moneda_id',
        'ultima_sincronizacion',
        'precio_referencial',
        'unidad_medida',
        'imagen',
        'activo',
        'estado',
        'stock',
        'categoria_id',
    ];

    public function cotizacionItems(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function inventarioMovimientos(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(ProductoSerie::class);
    }

    public function woocommerceProducto(): HasOne
    {
        return $this->hasOne(WooCommerceProducto::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'controla_stock' => 'boolean',
            'stock_actual' => 'decimal:2',
            'stock_reservado' => 'decimal:2',
            'stock_disponible' => 'decimal:2',
            'stock_minimo' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'costo_promedio' => 'decimal:4',
            'valor_stock' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'precio_referencial' => 'decimal:2',
            'ultima_sincronizacion' => 'datetime',
        ];
    }
}
