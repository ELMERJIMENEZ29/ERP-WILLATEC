<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MapearWooCommerceProductoRequest;
use App\Models\Producto;
use App\Models\WooCommerceProducto;
use App\Models\WooCommerceSyncLog;
use App\Services\WooCommerce\WooCommerceService;

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

    public function logs()
    {
        return response()->json(
            WooCommerceSyncLog::query()
                ->latest()
                ->paginate(request()->integer('per_page', 15))
        );
    }
}
