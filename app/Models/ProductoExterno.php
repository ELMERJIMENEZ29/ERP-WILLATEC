<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProductoExterno extends Model
{
    protected $table = 'productos_externos';

    protected $fillable = [
        'descripcion',
        'marca',
        'codigo',
        'unidad_medida',
        'proveedor',
        'link_proveedor',
        'costo_base_referencial',
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
            'costo_base_referencial' => 'decimal:2',
            'garantia_meses' => 'integer',
            'disponibilidad_dias' => 'integer',
            'stock' => 'integer',
        ];
    }
}
