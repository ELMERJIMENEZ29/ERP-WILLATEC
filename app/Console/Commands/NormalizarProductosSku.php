<?php

namespace App\Console\Commands;

use App\Services\ProductoSkuNormalizationService;
use Illuminate\Console\Command;

class NormalizarProductosSku extends Command
{
    protected $signature = 'productos:normalizar-skus
        {--dry-run : Solo previsualiza los cambios}
        {--apply : Aplica el lote}
        {--limit=25 : Cantidad maxima de productos a procesar}';

    protected $description = 'Previsualiza o aplica la normalizacion segura de SKUs legacy de productos.';

    public function handle(ProductoSkuNormalizationService $service): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $apply = (bool) $this->option('apply');

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('Usa --dry-run para previsualizar o --apply para aplicar.');

            return self::FAILURE;
        }

        $result = $apply
            ? $service->apply($limit)
            : $service->preview($limit);

        $rows = collect($result['items'] ?? [])->map(fn (array $row): array => [
            'ID' => $row['id'] ?? null,
            'codigo' => $row['codigo'] ?? null,
            'sku_actual' => $row['sku_actual'] ?? null,
            'sku_nuevo' => $row['sku_nuevo'] ?? null,
            'producto' => $row['producto'] ?? null,
            'woo' => ($row['vinculado_woocommerce'] ?? false) ? 'SI' : 'NO',
            'woo_product_id' => $row['woo_product_id'] ?? '-',
            'estado' => $row['estado'] ?? null,
            'mensaje' => $row['mensaje'] ?? null,
        ])->all();

        $this->table(
            ['ID', 'codigo', 'sku_actual', 'sku_nuevo', 'producto', 'woo', 'woo_product_id', 'estado', 'mensaje'],
            $rows
        );

        $this->info('Resumen: '.json_encode($result['resumen'] ?? [], JSON_UNESCAPED_UNICODE));
        $this->info('Legacy restante: '.($result['total_legacy_restante'] ?? $result['total_legacy'] ?? 0));

        return self::SUCCESS;
    }
}
