<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\WooCommerceSyncLog;
use App\Services\WooCommerce\WooCommerceService;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductoSkuNormalizationService
{
    public function __construct(
        private readonly ProductoSkuService $skuService,
        private readonly WooCommerceService $wooCommerceService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(int $limit = 25): array
    {
        $rows = $this->legacyQuery($limit)
            ->get()
            ->map(fn (Producto $producto): array => $this->buildPreviewRow($producto, true))
            ->values()
            ->all();

        return [
            'total_legacy' => $this->countLegacy(),
            'limit' => $limit,
            'items' => $rows,
            'resumen' => $this->summarize($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(int $limit = 25): array
    {
        $rows = [];

        $productos = $this->legacyQuery($limit)->get();

        foreach ($productos as $producto) {
            $rows[] = $this->applyProducto($producto);
        }

        return [
            'total_legacy_restante' => $this->countLegacy(),
            'limit' => $limit,
            'items' => $rows,
            'resumen' => $this->summarize($rows),
        ];
    }

    private function applyProducto(Producto $producto): array
    {
        $preview = $this->buildPreviewRow($producto, true);

        if (($preview['estado'] ?? null) !== 'PENDIENTE') {
            return $preview;
        }

        $skuNuevo = (string) $preview['sku_nuevo'];
        $mapping = $producto->woocommerceProducto;

        if ($mapping) {
            try {
                $conflict = $this->wooCommerceService->buscarProductoPorSku($skuNuevo);
                $conflictId = (int) ($conflict['id'] ?? 0);
                $currentWooId = (int) ($mapping->woo_variation_id ?: $mapping->woo_product_id);

                if ($conflictId > 0 && $conflictId !== $currentWooId) {
                    return array_merge($preview, [
                        'estado' => 'CONFLICTO_SKU_WOOCOMMERCE',
                        'mensaje' => "El SKU {$skuNuevo} ya pertenece al producto WooCommerce #{$conflictId}.",
                    ]);
                }

                $log = $this->wooCommerceService->actualizarSkuProductoMapeado($producto, $skuNuevo);

                if ($log->estado !== 'exitoso') {
                    return array_merge($preview, [
                        'estado' => 'ERROR_WOOCOMMERCE',
                        'mensaje' => $log->mensaje_error ?: 'WooCommerce no confirmo el cambio de SKU.',
                    ]);
                }
            } catch (Throwable $exception) {
                $this->logSkuUpdate($producto, $skuNuevo, 'error', $exception->getMessage());

                return array_merge($preview, [
                    'estado' => 'ERROR_WOOCOMMERCE',
                    'mensaje' => $exception->getMessage(),
                ]);
            }
        }

        try {
            DB::transaction(function () use ($producto, $skuNuevo, $mapping): void {
                $producto = Producto::query()
                    ->lockForUpdate()
                    ->with('woocommerceProducto')
                    ->findOrFail($producto->id);

                if (! $this->skuService->esSkuLegacy($producto->sku, $producto->codigo)) {
                    return;
                }

                $producto->forceFill(['sku' => $skuNuevo])->save();

                if ($mapping) {
                    $producto->woocommerceProducto?->forceFill([
                        'woo_sku' => $skuNuevo,
                        'last_sync_status' => 'exitoso',
                        'last_sync_error' => null,
                        'last_synced_at' => now(),
                    ])->save();
                }
            });
        } catch (Throwable $exception) {
            $this->logSkuUpdate($producto, $skuNuevo, 'error', $exception->getMessage());

            return array_merge($preview, [
                'estado' => 'ERROR_LOCAL',
                'mensaje' => $exception->getMessage(),
            ]);
        }

        $this->logSkuUpdate($producto, $skuNuevo, 'exitoso', null);

        return array_merge($preview, [
            'estado' => 'APLICADO',
            'mensaje' => $mapping ? 'SKU actualizado en ERP y WooCommerce.' : 'SKU actualizado en ERP.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPreviewRow(Producto $producto, bool $checkingWoo): array
    {
        $producto->loadMissing(['categoria', 'woocommerceProducto']);

        $skuNuevo = $this->skuService->generarSkuParaProducto($producto);
        $mapping = $producto->woocommerceProducto;
        $estado = 'PENDIENTE';
        $mensaje = null;

        if (! $this->skuService->esSkuLegacy($producto->sku, $producto->codigo)) {
            $estado = 'CONSERVAR';
            $skuNuevo = null;
        } elseif ($skuNuevo && Producto::query()
            ->where('sku', $skuNuevo)
            ->where('id', '<>', $producto->id)
            ->exists()) {
            $estado = 'CONFLICTO_SKU_LOCAL';
            $mensaje = "El SKU {$skuNuevo} ya existe localmente.";
        } elseif ($checkingWoo && $mapping && $skuNuevo) {
            $conflict = $this->wooCommerceService->buscarProductoPorSku($skuNuevo);
            $conflictId = (int) ($conflict['id'] ?? 0);
            $currentWooId = (int) ($mapping->woo_variation_id ?: $mapping->woo_product_id);

            if ($conflictId > 0 && $conflictId !== $currentWooId) {
                $estado = 'CONFLICTO_SKU_WOOCOMMERCE';
                $mensaje = "El SKU {$skuNuevo} ya pertenece al producto WooCommerce #{$conflictId}.";
            }
        }

        return [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'sku_actual' => $producto->sku,
            'sku_nuevo' => $skuNuevo,
            'producto' => $producto->nombre,
            'categoria' => $producto->categoria?->nombre,
            'marca' => $producto->marca,
            'modelo' => $producto->modelo,
            'vinculado_woocommerce' => (bool) $mapping,
            'woo_product_id' => $mapping?->woo_product_id,
            'woo_variation_id' => $mapping?->woo_variation_id,
            'estado' => $estado,
            'mensaje' => $mensaje,
        ];
    }

    private function legacyQuery(int $limit)
    {
        return Producto::query()
            ->with(['categoria', 'woocommerceProducto'])
            ->where(function ($query): void {
                $query->whereNull('sku')
                    ->orWhere('sku', '')
                    ->orWhereColumn('sku', 'codigo');
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 100)));
    }

    private function countLegacy(): int
    {
        return Producto::query()
            ->where(function ($query): void {
                $query->whereNull('sku')
                    ->orWhere('sku', '')
                    ->orWhereColumn('sku', 'codigo');
            })
            ->count();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summarize(array $rows): array
    {
        return collect($rows)
            ->groupBy(fn (array $row): string => (string) ($row['estado'] ?? 'DESCONOCIDO'))
            ->map(fn ($items): int => $items->count())
            ->all();
    }

    private function logSkuUpdate(Producto $producto, string $skuNuevo, string $estado, ?string $mensaje): void
    {
        WooCommerceSyncLog::create([
            'tipo' => 'sku_update',
            'direccion' => 'erp_to_woocommerce',
            'endpoint' => null,
            'payload' => [
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'sku_anterior' => $producto->sku,
                'sku_nuevo' => $skuNuevo,
            ],
            'estado' => $estado,
            'mensaje_error' => $mensaje,
            'referencia_tipo' => Producto::class,
            'referencia_id' => $producto->id,
        ]);
    }
}
