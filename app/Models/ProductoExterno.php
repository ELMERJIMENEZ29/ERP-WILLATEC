<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ProductoExterno extends Model
{
    protected $table = 'productos_externos';

    protected $fillable = [
        'producto_id',
        'descripcion',
        'marca',
        'codigo',
        'unidad_medida',
        'proveedor',
        'link_proveedor',
        'costo_base_referencial',
        'moneda_id',
        'precio_incluye_igv',
        'plantilla_origen_id',
        'imagen',
        'garantia_meses',
        'disponibilidad_tipo',
        'disponibilidad_dias',
        'stock',
        'fingerprint',
        'activo',
    ];

    public function cotizacionItems(): HasMany
    {
        return $this->hasMany(CotizacionItem::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    public function plantillaOrigen(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class, 'plantilla_origen_id');
    }

    public function ultimoCotizacionItem(): HasOne
    {
        return $this->hasOne(CotizacionItem::class)->latestOfMany();
    }

    public function ultimoCotizacionItemConProveedores(): HasOne
    {
        return $this->hasOne(CotizacionItem::class)
            ->whereHas('proveedores')
            ->latestOfMany();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fingerprintFrom(array $data): string
    {
        $codigo = self::normalizeFingerprintValue($data['codigo'] ?? null);
        $marca = self::normalizeFingerprintValue($data['marca'] ?? null);
        $proveedor = self::normalizeFingerprintValue($data['proveedor'] ?? null);

        $identity = $codigo !== ''
            ? ['codigo', $codigo, $marca, $proveedor]
            : [
                'descripcion',
                self::normalizeFingerprintValue($data['descripcion'] ?? null),
                $marca,
                $proveedor,
            ];

        return hash('sha256', implode('|', $identity));
    }

    private static function normalizeFingerprintValue(mixed $value): string
    {
        $normalized = Str::of((string) ($value ?? ''))
            ->lower()
            ->squish()
            ->toString();

        return trim($normalized);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'producto_id' => 'integer',
            'costo_base_referencial' => 'decimal:2',
            'moneda_id' => 'integer',
            'precio_incluye_igv' => 'boolean',
            'plantilla_origen_id' => 'integer',
            'garantia_meses' => 'integer',
            'disponibilidad_dias' => 'integer',
            'stock' => 'integer',
        ];
    }
}
