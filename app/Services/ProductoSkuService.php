<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Str;

class ProductoSkuService
{
    private const CATEGORY_ALIASES = [
        'laptop' => 'LAP',
        'laptops' => 'LAP',
        'monitor' => 'MON',
        'monitores' => 'MON',
        'impresora' => 'IMP',
        'impresoras' => 'IMP',
        'mouse' => 'MOU',
        'mouses' => 'MOU',
        'teclado' => 'TEC',
        'teclados' => 'TEC',
        'celular' => 'CEL',
        'celulares' => 'CEL',
        'ssd' => 'SSD',
        'ram' => 'RAM',
        'perifericos' => 'PER',
        'periferico' => 'PER',
        'accesorios' => 'ACC',
        'computadoras' => 'COM',
        'licencias' => 'LIC',
        'servidores' => 'SRV',
        'gadgets' => 'GAD',
        'suministros' => 'SUM',
        'redes' => 'RED',
        'seguridad' => 'SEG',
        'componentes' => 'CMP',
        'almacenamiento' => 'ALM',
    ];

    private const BRAND_ALIASES = [
        'lenovo' => 'LEN',
        'hp' => 'HP',
        'hewlett packard' => 'HP',
        'samsung' => 'SAM',
        'logitech' => 'LOG',
        'kingston' => 'KIN',
        'motorola' => 'MOT',
        'dell' => 'DEL',
        'asus' => 'ASU',
        'acer' => 'ACE',
        'epson' => 'EPS',
        'canon' => 'CAN',
        'lg' => 'LG',
        'microsoft' => 'MIC',
        'genius' => 'GEN',
    ];

    public function generarSkuParaProducto(Producto $producto, ?string $skuActual = null): string
    {
        $categoria = $producto->categoria?->nombre ?: (string) $producto->categoria_id;
        $marca = (string) ($producto->marca ?: 'GEN');
        $modelo = (string) ($producto->modelo ?: $producto->nombre ?: $producto->codigo ?: 'PROD');

        return $this->generarSku(
            categoria: $categoria,
            marca: $marca,
            modelo: $modelo,
            ignoreProductoId: $producto->id,
            skuActual: $skuActual
        );
    }

    public function generarSku(
        ?string $categoria,
        ?string $marca,
        ?string $modelo,
        ?int $ignoreProductoId = null,
        ?string $skuActual = null
    ): string {
        $base = implode('-', array_filter([
            $this->abreviarCategoria($categoria),
            $this->abreviarMarca($marca),
            $this->normalizarModelo($modelo),
        ]));

        if ($base === '') {
            $base = 'PROD-GEN-ITEM';
        }

        return $this->generarCorrelativo($base, $ignoreProductoId, $skuActual);
    }

    public function esSkuLegacy(?string $sku, ?string $codigo): bool
    {
        $sku = trim((string) $sku);

        return $sku === '' || ($codigo !== null && $sku === trim((string) $codigo));
    }

    public function normalizarSegmento(?string $value, int $maxLength = 16): string
    {
        $normalized = Str::ascii((string) $value);
        $normalized = strtoupper($normalized);
        $normalized = preg_replace('/[^A-Z0-9]+/', '-', $normalized) ?: '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $normalized)));
        $result = '';

        foreach ($parts as $part) {
            $candidate = $result === '' ? $part : $result.'-'.$part;

            if (strlen($candidate) > $maxLength) {
                if ($result === '') {
                    return substr($part, 0, $maxLength);
                }

                break;
            }

            $result = $candidate;
        }

        return $result;
    }

    public function abreviarCategoria(?string $categoria): string
    {
        $key = $this->lookupKey($categoria);

        return self::CATEGORY_ALIASES[$key] ?? $this->abreviarTexto($categoria, 3, 'CAT');
    }

    public function abreviarMarca(?string $marca): string
    {
        $key = $this->lookupKey($marca);

        return self::BRAND_ALIASES[$key] ?? $this->abreviarTexto($marca, 3, 'GEN');
    }

    public function normalizarModelo(?string $modelo): string
    {
        $modelo = $this->normalizarSegmento($modelo, 30);

        if ($modelo === '') {
            return 'ITEM';
        }

        $tokens = array_values(array_filter(explode('-', $modelo)));

        if (count($tokens) <= 1) {
            return substr($modelo, 0, 18);
        }

        $compact = collect($tokens)
            ->reject(fn (string $token): bool => in_array($token, ['THINKPAD', 'LASERJET', 'ODYSSEY'], true))
            ->map(function (string $token): string {
                if ($token === 'GEN') {
                    return 'G';
                }

                return $token;
            })
            ->values()
            ->join('');

        if ($compact !== '' && strlen($compact) <= 12) {
            return $compact;
        }

        return substr($modelo, 0, 18);
    }

    private function generarCorrelativo(string $base, ?int $ignoreProductoId, ?string $skuActual): string
    {
        if ($skuActual && ! $this->skuExiste($skuActual, $ignoreProductoId)) {
            return $skuActual;
        }

        for ($index = 1; $index <= 999; $index++) {
            $sku = $base.'-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);

            if (! $this->skuExiste($sku, $ignoreProductoId)) {
                return $sku;
            }
        }

        return $base.'-'.strtoupper(Str::random(6));
    }

    private function skuExiste(string $sku, ?int $ignoreProductoId = null): bool
    {
        return Producto::query()
            ->where('sku', $sku)
            ->when($ignoreProductoId, fn ($query) => $query->where('id', '<>', $ignoreProductoId))
            ->exists();
    }

    private function lookupKey(?string $value): string
    {
        return str_replace('-', ' ', strtolower($this->normalizarSegmento($value, 50)));
    }

    private function abreviarTexto(?string $value, int $length, string $fallback): string
    {
        $normalized = str_replace('-', '', $this->normalizarSegmento($value, 20));

        if ($normalized === '') {
            return $fallback;
        }

        return substr($normalized, 0, $length);
    }
}
