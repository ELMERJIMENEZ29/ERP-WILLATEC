<?php

namespace App\Services\WooCommerce;

use App\Models\Producto;
use App\Models\WooCommerceProducto;
use App\Models\WooCommerceSyncLog;
use App\Services\ProductoSkuService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WooCommerceService
{
    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return config('services.woocommerce', []);
    }

    public function estaConfigurado(): bool
    {
        $config = $this->config();

        return filled($config['url'] ?? null)
            && filled($config['consumer_key'] ?? null)
            && filled($config['consumer_secret'] ?? null);
    }

    public function actualizarStock(Producto $producto): WooCommerceSyncLog
    {
        $mapping = $producto->woocommerceProducto;

        if (! $mapping) {
            throw new RuntimeException('El producto no tiene mapeo WooCommerce.');
        }

        if (! $this->estaConfigurado()) {
            return WooCommerceSyncLog::create([
                'tipo' => 'producto_stock',
                'direccion' => 'erp_to_woocommerce',
                'endpoint' => null,
                'payload' => [
                    'producto_id' => $producto->id,
                    'sku' => $producto->sku,
                    'stock_quantity' => (float) $producto->stock_disponible,
                ],
                'estado' => 'pendiente',
                'mensaje_error' => 'WooCommerce no esta configurado.',
                'referencia_tipo' => Producto::class,
                'referencia_id' => $producto->id,
            ]);
        }

        $endpoint = $this->productoEndpoint($mapping->woo_product_id, $mapping->woo_variation_id, $mapping->woo_parent_id);
        $payload = [
            'manage_stock' => $mapping->manage_stock,
            'stock_quantity' => (float) $producto->stock_disponible,
        ];

        $response = $this->request()->put($endpoint, $payload);

        $log = WooCommerceSyncLog::create([
            'tipo' => 'producto_stock',
            'direccion' => 'erp_to_woocommerce',
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response' => $response->json(),
            'status_code' => $response->status(),
            'estado' => $response->successful() ? 'exitoso' : 'error',
            'mensaje_error' => $response->successful() ? null : $response->body(),
            'referencia_tipo' => Producto::class,
            'referencia_id' => $producto->id,
        ]);

        $mapping->update([
            'last_stock_sent' => $producto->stock_disponible,
            'last_sync_status' => $log->estado,
            'last_sync_error' => $log->mensaje_error,
            'last_synced_at' => now(),
        ]);

        $producto->update(['ultima_sincronizacion' => now()]);

        return $log;
    }

    public function actualizarStockSiEstaMapeado(Producto $producto): ?WooCommerceSyncLog
    {
        $producto->loadMissing('woocommerceProducto');

        if (! $producto->woocommerceProducto) {
            return null;
        }

        try {
            return $this->actualizarStock($producto);
        } catch (\Throwable $exception) {
            Log::warning('No se pudo sincronizar stock con WooCommerce', [
                'producto_id' => $producto->id,
                'error' => $exception->getMessage(),
            ]);

            return WooCommerceSyncLog::create([
                'tipo' => 'producto_stock',
                'direccion' => 'erp_to_woocommerce',
                'endpoint' => null,
                'payload' => [
                    'producto_id' => $producto->id,
                    'sku' => $producto->sku,
                    'stock_quantity' => (float) $producto->stock_disponible,
                ],
                'estado' => 'error',
                'mensaje_error' => $exception->getMessage(),
                'referencia_tipo' => Producto::class,
                'referencia_id' => $producto->id,
            ]);
        }
    }

    public function mapearProductoPorSku(Producto $producto): WooCommerceProducto
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('WooCommerce no esta configurado.');
        }

        $sku = trim((string) ($producto->sku ?: $producto->codigo));

        if ($sku === '') {
            throw new RuntimeException('El producto no tiene SKU o codigo para buscar en WooCommerce.');
        }

        if (app(ProductoSkuService::class)->esSkuLegacy($producto->sku, $producto->codigo)) {
            throw new RuntimeException('Este producto aun usa un SKU legacy igual al codigo interno. Primero previsualiza y aplica la normalizacion de SKU antes de conectarlo con WooCommerce.');
        }

        $wooProducto = $this->buscarProductoPorSku($sku);

        if (! $wooProducto) {
            $wooProducto = $this->crearProductoWooCommerce($producto, $sku);
        }

        $wooProductId = (int) ($wooProducto['id'] ?? 0);
        $wooParentId = isset($wooProducto['parent_id']) ? (int) $wooProducto['parent_id'] : null;
        $wooVariationId = ($wooParentId && $wooParentId !== $wooProductId) ? $wooProductId : null;

        if ($wooProductId <= 0) {
            throw new RuntimeException('WooCommerce no devolvio un ID de producto valido.');
        }

        return WooCommerceProducto::updateOrCreate(
            [
                'producto_id' => $producto->id,
                'woocommerce_store_id' => null,
            ],
            [
                'woo_product_id' => $wooVariationId ? $wooParentId : $wooProductId,
                'woo_variation_id' => $wooVariationId,
                'woo_parent_id' => $wooVariationId ? $wooParentId : null,
                'woo_sku' => (string) ($wooProducto['sku'] ?? $sku),
                'manage_stock' => true,
            ]
        );
    }

    public function actualizarSkuProductoMapeado(Producto $producto, string $skuNuevo): WooCommerceSyncLog
    {
        $mapping = $producto->loadMissing('woocommerceProducto')->woocommerceProducto;

        if (! $mapping) {
            throw new RuntimeException('El producto no tiene mapeo WooCommerce.');
        }

        if (! $this->estaConfigurado()) {
            return WooCommerceSyncLog::create([
                'tipo' => 'sku_update',
                'direccion' => 'erp_to_woocommerce',
                'endpoint' => null,
                'payload' => [
                    'producto_id' => $producto->id,
                    'sku_anterior' => $producto->sku,
                    'sku_nuevo' => $skuNuevo,
                ],
                'estado' => 'pendiente',
                'mensaje_error' => 'WooCommerce no esta configurado.',
                'referencia_tipo' => Producto::class,
                'referencia_id' => $producto->id,
            ]);
        }

        $endpoint = $this->productoEndpoint($mapping->woo_product_id, $mapping->woo_variation_id, $mapping->woo_parent_id);
        $payload = ['sku' => $skuNuevo];
        $response = $this->request()->put($endpoint, $payload);

        $log = WooCommerceSyncLog::create([
            'tipo' => 'sku_update',
            'direccion' => 'erp_to_woocommerce',
            'endpoint' => $endpoint,
            'payload' => [
                'producto_id' => $producto->id,
                'woo_product_id' => $mapping->woo_product_id,
                'woo_variation_id' => $mapping->woo_variation_id,
                'sku_anterior' => $producto->sku,
                'sku_nuevo' => $skuNuevo,
            ],
            'response' => $response->json(),
            'status_code' => $response->status(),
            'estado' => $response->successful() ? 'exitoso' : 'error',
            'mensaje_error' => $response->successful() ? null : $response->body(),
            'referencia_tipo' => Producto::class,
            'referencia_id' => $producto->id,
        ]);

        if ($response->successful()) {
            $mapping->update([
                'woo_sku' => $skuNuevo,
                'last_sync_status' => 'exitoso',
                'last_sync_error' => null,
                'last_synced_at' => now(),
            ]);
        }

        return $log;
    }

    private function crearProductoWooCommerce(Producto $producto, string $sku): array
    {
        $endpoint = '/wp-json/wc/v3/products';
        $payload = [
            'name' => trim((string) ($producto->nombre ?: $sku)),
            'type' => 'simple',
            'sku' => $sku,
            'status' => $this->defaultProductStatus(),
            'manage_stock' => true,
            'stock_quantity' => (float) ($producto->stock_disponible ?? 0),
            'description' => (string) ($producto->descripcion ?? ''),
        ];

        $regularPrice = $this->regularPrice($producto);
        if ($regularPrice !== null) {
            $payload['regular_price'] = $regularPrice;
        }

        $response = $this->request()->post($endpoint, $payload);

        WooCommerceSyncLog::create([
            'tipo' => 'producto_create',
            'direccion' => 'erp_to_woocommerce',
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response' => $response->json(),
            'status_code' => $response->status(),
            'estado' => $response->successful() ? 'exitoso' : 'error',
            'mensaje_error' => $response->successful() ? null : $response->body(),
            'referencia_tipo' => Producto::class,
            'referencia_id' => $producto->id,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo crear el producto en WooCommerce: '.$response->body());
        }

        return $response->json();
    }

    private function defaultProductStatus(): string
    {
        $status = (string) (config('services.woocommerce.default_product_status') ?: 'draft');

        return in_array($status, ['draft', 'publish', 'pending', 'private'], true)
            ? $status
            : 'draft';
    }

    private function regularPrice(Producto $producto): ?string
    {
        $price = (float) ($producto->precio_venta ?: $producto->precio_referencial ?: 0);

        if ($price <= 0) {
            return null;
        }

        return number_format($price, 2, '.', '');
    }

    public function buscarProductoPorSku(string $sku): ?array
    {
        if (! $this->estaConfigurado()) {
            return null;
        }

        $response = $this->request()->get('/wp-json/wc/v3/products', [
            'sku' => $sku,
        ]);

        if (! $response->successful()) {
            return null;
        }

        return $response->json()[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function obtenerPedidos(array $params = []): array
    {
        if (! $this->estaConfigurado()) {
            throw new RuntimeException('WooCommerce no esta configurado.');
        }

        $response = $this->request()->get('/wp-json/wc/v3/orders', $params);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudieron obtener pedidos WooCommerce: '.$response->body());
        }

        return $response->json() ?: [];
    }

    private function request(): PendingRequest
    {
        $config = $this->config();

        return Http::baseUrl(rtrim((string) $config['url'], '/'))
            ->withBasicAuth((string) $config['consumer_key'], (string) $config['consumer_secret'])
            ->acceptJson()
            ->asJson();
    }

    private function productoEndpoint(int $wooProductId, ?int $variationId, ?int $parentId): string
    {
        if ($variationId && $parentId) {
            return "/wp-json/wc/v3/products/{$parentId}/variations/{$variationId}";
        }

        return "/wp-json/wc/v3/products/{$wooProductId}";
    }
}
