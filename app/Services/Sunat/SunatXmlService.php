<?php

namespace App\Services\Sunat;

use App\Models\Comprobante;
use App\Models\Moneda;
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
        ];
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
