<?php

namespace App\Services\Sunat;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Models\Moneda;
use App\Models\OcRecibida;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;

class SunatXmlService
{
    public function __construct(
        private readonly FacturaUblParser $parser
    ) {}

    public function preview(UploadedFile $file): array
    {
        $xml = (string) file_get_contents($file->getRealPath());
        $hash = hash('sha256', $xml);
        $data = $this->parser->parse($xml);

        $companyRuc = trim((string) config('app.company_ruc', env('COMPANY_RUC', '')));
        $tipoOperacion = 'observado';

        if ($companyRuc !== '') {
            if (($data['emisor_ruc'] ?? null) === $companyRuc) {
                $tipoOperacion = Comprobante::TIPO_OPERACION_VENTA;
            } elseif (($data['receptor_ruc'] ?? null) === $companyRuc) {
                $tipoOperacion = Comprobante::TIPO_OPERACION_COMPRA;
            }
        }

        return [
            ...$data,
            'tipo_operacion_sugerida' => $tipoOperacion,
            'company_ruc_configurado' => $companyRuc !== '',
            'xml_hash' => $hash,
            'moneda_id' => $this->monedaId($data['moneda_codigo'] ?? null),
            'duplicado' => $this->duplicado($data, $hash),
            'vinculos_sugeridos' => $this->vinculosSugeridos($data, $tipoOperacion),
        ];
    }

    private function vinculosSugeridos(array $data, string $tipoOperacion): array
    {
        if ($tipoOperacion === Comprobante::TIPO_OPERACION_COMPRA) {
            $proveedorId = Proveedor::query()->where('ruc', $data['emisor_ruc'] ?? '')->value('id');

            return Compra::query()
                ->where('proveedor_id', $proveedorId)
                ->whereIn('estado', [Compra::ESTADO_CONFIRMADA, Compra::ESTADO_PARCIALMENTE_RECIBIDA, Compra::ESTADO_RECIBIDA])
                ->latest('id')->limit(50)->get(['id', 'numero', 'estado', 'fecha_compra', 'total_estimado'])
                ->map(fn (Compra $compra): array => [
                    'id' => $compra->id,
                    'label' => "{$compra->numero} · {$compra->estado} · {$compra->fecha_compra}",
                ])->all();
        }

        if ($tipoOperacion === Comprobante::TIPO_OPERACION_VENTA) {
            $clienteId = Cliente::query()->where('ruc', $data['receptor_ruc'] ?? '')->value('id');

            return OcRecibida::query()
                ->where('cliente_id', $clienteId)
                ->latest('id')->limit(50)->get(['id', 'numero', 'estado', 'cotizacion_id'])
                ->map(fn (OcRecibida $oc): array => [
                    'id' => $oc->id,
                    'label' => "{$oc->numero} · {$oc->estado}",
                ])->all();
        }

        return [];
    }

    private function monedaId(?string $codigo): ?int
    {
        if (! $codigo) {
            return null;
        }

        return Moneda::query()
            ->whereRaw('UPPER(codigo) = ?', [mb_strtoupper(trim($codigo), 'UTF-8')])
            ->value('id');
    }

    private function duplicado(array $data, string $hash): array
    {
        $porHash = Comprobante::query()
            ->where('xml_hash', $hash)
            ->first();

        $porSerie = null;

        if (! empty($data['emisor_ruc']) && ! empty($data['serie']) && ! empty($data['numero'])) {
            $porSerie = Comprobante::query()
                ->where('emisor_ruc', $data['emisor_ruc'])
                ->where('tipo_comprobante', $data['tipo_comprobante'])
                ->where('serie', $data['serie'])
                ->where('numero', $data['numero'])
                ->first();
        }

        return [
            'existe' => (bool) ($porHash || $porSerie),
            'por_hash' => (bool) $porHash,
            'por_serie_numero' => (bool) $porSerie,
            'comprobante_id' => $porHash?->id ?? $porSerie?->id,
        ];
    }
}
