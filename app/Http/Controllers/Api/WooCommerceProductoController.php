<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapearWooCommerceProductoRequest;
use App\Models\Producto;
use App\Models\WooCommerceProducto;
use App\Models\WooCommerceSyncLog;
use App\Services\WooCommerce\WooCommerceService;
use Illuminate\Http\Request;

class WooCommerceProductoController extends Controller
{
    public function mapear(MapearWooCommerceProductoRequest $request)
    {
        $data = $request->validated();

        $mapping = WooCommerceProducto::updateOrCreate(
            [
                'producto_id' => $data['producto_id'],
                'woocommerce_store_id' => $data['woocommerce_store_id'] ?? null,
            ],
            [
                'woo_product_id' => $data['woo_product_id'],
                'woo_variation_id' => $data['woo_variation_id'] ?? null,
                'woo_parent_id' => $data['woo_parent_id'] ?? null,
                'woo_sku' => $data['woo_sku'],
                'manage_stock' => $request->boolean('manage_stock', true),
            ]
        );

        return response()->json([
            'message' => 'Producto mapeado con WooCommerce',
            'mapping' => $mapping->load('producto'),
        ]);
    }

    public function sincronizarStock(Producto $producto, WooCommerceService $wooCommerceService)
    {
        $log = $wooCommerceService->actualizarStock($producto->loadMissing('woocommerceProducto'));

        return response()->json([
            'message' => 'Sincronizacion de stock registrada',
            'log' => $log,
        ]);
    }

    public function mapearPorSku(Producto $producto, WooCommerceService $wooCommerceService)
    {
        $mapping = $wooCommerceService->mapearProductoPorSku($producto);
        $log = $wooCommerceService->actualizarStock($producto->fresh('woocommerceProducto'));

        return response()->json([
            'message' => 'Producto conectado con WooCommerce y stock sincronizado',
            'mapping' => $mapping->fresh('producto'),
            'log' => $log,
        ]);
    }

    public function sincronizarActivos(Request $request, WooCommerceService $wooCommerceService)
    {
        $limit = min(max($request->integer('limit', 100), 1), 200);
        $productos = Producto::query()
            ->where('activo', true)
            ->where('controla_stock', true)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $resumen = [
            'procesados' => 0,
            'exitosos' => 0,
            'errores' => 0,
            'limite' => $limit,
            'errores_detalle' => [],
        ];

        foreach ($productos as $producto) {
            $resumen['procesados']++;

            try {
                $wooCommerceService->mapearProductoPorSku($producto);
                $log = $wooCommerceService->actualizarStock($producto->fresh('woocommerceProducto'));

                if ($log->estado === 'exitoso') {
                    $resumen['exitosos']++;
                } else {
                    $resumen['errores']++;
                    $resumen['errores_detalle'][] = [
                        'producto_id' => $producto->id,
                        'sku' => $producto->sku ?: $producto->codigo,
                        'mensaje' => $log->mensaje_error ?: 'WooCommerce no confirmo la sincronizacion.',
                    ];
                }
            } catch (\Throwable $exception) {
                $resumen['errores']++;
                $resumen['errores_detalle'][] = [
                    'producto_id' => $producto->id,
                    'sku' => $producto->sku ?: $producto->codigo,
                    'mensaje' => $exception->getMessage(),
                ];

                WooCommerceSyncLog::create([
                    'tipo' => 'producto_stock',
                    'direccion' => 'erp_to_woocommerce',
                    'endpoint' => null,
                    'payload' => [
                        'producto_id' => $producto->id,
                        'sku' => $producto->sku ?: $producto->codigo,
                        'stock_quantity' => (float) ($producto->stock_disponible ?? 0),
                    ],
                    'estado' => 'error',
                    'mensaje_error' => $exception->getMessage(),
                    'referencia_tipo' => Producto::class,
                    'referencia_id' => $producto->id,
                ]);
            }
        }

        return response()->json([
            'message' => "Sincronizacion WooCommerce finalizada: {$resumen['exitosos']} exitosos, {$resumen['errores']} con error.",
            'resumen' => $resumen,
        ]);
    }

    public function logs()
    {
        return response()->json(
            WooCommerceSyncLog::query()
                ->latest()
                ->paginate(request()->integer('per_page', 15))
        );
    }
}
