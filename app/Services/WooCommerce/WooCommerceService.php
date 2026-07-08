<?php

namespace App\Services\WooCommerce;

use App\Models\Producto;
use App\Models\WooCommerceSyncLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
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
