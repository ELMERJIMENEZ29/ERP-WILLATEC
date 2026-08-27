<?php

use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\WooCommercePedido;
use App\Services\WooCommerce\WooCommercePedidoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('importa pedido woocommerce por sku y reserva stock de forma idempotente', function () {
    $producto = Producto::create([
        'nombre' => 'Mouse Genius DX-110',
        'sku' => 'MOU-GEN-DX110-001',
        'codigo' => '0001',
        'controla_stock' => true,
        'stock_actual' => 5,
        'stock_reservado' => 0,
        'stock_disponible' => 5,
        'stock' => 5,
        'activo' => true,
    ]);

    $service = app(WooCommercePedidoService::class);
    $pedido = $service->importarPedido([
        'id' => 9001,
        'number' => '9001',
        'status' => 'processing',
        'currency' => 'USD',
        'total' => '100.00',
        'date_created_gmt' => '2026-08-27T15:00:00',
        'billing' => [
            'first_name' => 'Cliente',
            'last_name' => 'Demo',
            'email' => 'cliente@example.com',
            'phone' => '999999999',
        ],
        'line_items' => [
            [
                'id' => 10,
                'product_id' => 50,
                'variation_id' => 0,
                'sku' => 'MOU-GEN-DX110-001',
                'name' => 'Mouse Genius DX-110',
                'quantity' => 2,
                'total' => '100.00',
            ],
        ],
    ]);

    expect($pedido->estado_erp)->toBe(WooCommercePedido::ESTADO_LISTO_RESERVA)
        ->and($pedido->items)->toHaveCount(1)
        ->and($pedido->items->first()->producto_id)->toBe($producto->id);

    $request = Request::create('/api/woocommerce/pedidos/1/reservar', 'POST');

    $service->reservarPedido($pedido, $request);
    $service->reservarPedido($pedido->fresh(), $request);

    $producto->refresh();

    expect((float) $producto->stock_reservado)->toBe(2.0)
        ->and((float) $producto->stock_disponible)->toBe(3.0)
        ->and(InventarioMovimiento::query()->where('referencia_tipo', 'woocommerce_pedido')->count())->toBe(1);
});
