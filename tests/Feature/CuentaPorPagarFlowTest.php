<?php

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Comprobante;
use App\Models\CuentaPorPagar;
use App\Models\Moneda;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('genera cuenta por pagar desde comprobante de compra de forma idempotente', function () {
    $base = crearBaseCuentaPorPagarFase7();

    Sanctum::actingAs($base['contabilidad']);

    $response = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-pagar", [
        'fecha_vencimiento' => '2026-09-13',
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorPagar::ESTADO_PENDIENTE)
        ->assertJsonPath('total', '118.00')
        ->assertJsonPath('saldo', '118.00');

    $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-pagar", [
        'fecha_vencimiento' => '2026-09-13',
    ])
        ->assertCreated()
        ->assertJsonPath('id', $response->json('id'));

    expect(CuentaPorPagar::query()->count())->toBe(1);
});

test('no genera cuenta por pagar desde comprobante de venta', function () {
    $base = crearBaseCuentaPorPagarFase7();

    Sanctum::actingAs($base['contabilidad']);

    $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteVenta']->id}/cuenta-por-pagar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comprobante_id');
});

test('permite pagos parciales y bloquea sobrepago', function () {
    $base = crearBaseCuentaPorPagarFase7();

    Sanctum::actingAs($base['contabilidad']);

    $cuentaId = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-pagar")
        ->assertCreated()
        ->json('id');

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", [
        'monto' => 50,
        'referencia' => 'OP-001',
        'idempotency_key' => 'pago-cxp-1',
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorPagar::ESTADO_PARCIAL)
        ->assertJsonPath('monto_pagado', '50.00')
        ->assertJsonPath('saldo', '68.00');

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", [
        'monto' => 69,
        'referencia' => 'OP-002',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monto');

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", [
        'monto' => 68,
        'referencia' => 'OP-003',
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorPagar::ESTADO_PAGADA)
        ->assertJsonPath('saldo', '0.00');
});

test('pago con misma idempotency key no duplica saldo', function () {
    $base = crearBaseCuentaPorPagarFase7();

    Sanctum::actingAs($base['contabilidad']);

    $cuentaId = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-pagar")
        ->assertCreated()
        ->json('id');

    $payload = [
        'monto' => 40,
        'idempotency_key' => 'pago-reintento-1',
    ];

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", $payload)
        ->assertCreated()
        ->assertJsonPath('monto_pagado', '40.00');

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", $payload)
        ->assertCreated()
        ->assertJsonPath('monto_pagado', '40.00');

    expect(Pago::query()->count())->toBe(1);
});

test('anular pago recalcula saldo', function () {
    $base = crearBaseCuentaPorPagarFase7();

    Sanctum::actingAs($base['contabilidad']);

    $cuentaId = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-pagar")
        ->assertCreated()
        ->json('id');

    $this->postJson("/api/contabilidad/cuentas-por-pagar/{$cuentaId}/pagos", [
        'monto' => 118,
    ])->assertCreated();

    $pagoId = Pago::query()->firstOrFail()->id;

    $this->patchJson("/api/contabilidad/pagos/{$pagoId}/anular")
        ->assertOk()
        ->assertJsonPath('estado', CuentaPorPagar::ESTADO_PENDIENTE)
        ->assertJsonPath('saldo', '118.00');
});

test('ventas no puede acceder a cuentas por pagar', function () {
    crearBaseCuentaPorPagarFase7();

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    Sanctum::actingAs($ventas);

    $this->getJson('/api/contabilidad/cuentas-por-pagar')
        ->assertForbidden();
});

function crearBaseCuentaPorPagarFase7(): array
{
    test()->seed(RoleSeeder::class);

    $moneda = Moneda::create([
        'codigo' => 'PEN',
        'simbolo' => 'S/',
    ]);

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    $proveedor = Proveedor::create([
        'nombre' => 'Proveedor CxP',
        'ruc' => '20111111111',
        'activo' => true,
    ]);

    $tipoCliente = TipoCliente::create([
        'nombre' => 'Activo',
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Cliente CxP',
        'ruc' => '20999999991',
        'estado' => 'activo',
        'tipo_cliente_id' => $tipoCliente->id,
        'moneda_id' => $moneda->id,
    ]);

    $producto = Producto::create([
        'nombre' => 'Laptop CxP',
        'sku' => 'CXP-001',
        'codigo' => 'CXP-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 0,
        'stock_reservado' => 0,
        'stock_disponible' => 0,
        'stock' => 0,
        'activo' => true,
        'costo_unitario' => 100,
        'costo_promedio' => 100,
        'valor_stock' => 0,
        'moneda_id' => $moneda->id,
    ]);

    $compra = Compra::create([
        'numero' => 'CMP-CXP-001',
        'proveedor_id' => $proveedor->id,
        'modalidad' => Compra::MODALIDAD_DIRECTA,
        'estado' => Compra::ESTADO_CONFIRMADA,
        'fecha_compra' => '2026-08-13',
        'moneda_id' => $moneda->id,
        'creado_por' => $contabilidad->id,
    ]);

    $compraItem = CompraItem::create([
        'compra_id' => $compra->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad' => 1,
        'cantidad_recibida' => 0,
        'costo_unitario_estimado' => 100,
        'moneda_id' => $moneda->id,
        'estado' => CompraItem::ESTADO_PENDIENTE,
    ]);

    $comprobanteCompra = Comprobante::create([
        'tipo_operacion' => Comprobante::TIPO_OPERACION_COMPRA,
        'compra_id' => $compra->id,
        'proveedor_id' => $proveedor->id,
        'emisor_ruc' => $proveedor->ruc,
        'tipo_comprobante' => 'factura',
        'serie' => 'F001',
        'numero' => '700',
        'fecha_emision' => '2026-08-13',
        'fecha_vencimiento' => '2026-09-13',
        'moneda_id' => $moneda->id,
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
        'estado' => Comprobante::ESTADO_REGISTRADO,
        'creado_por' => $contabilidad->id,
    ]);

    $comprobanteCompra->items()->create([
        'compra_item_id' => $compraItem->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad' => 1,
        'valor_unitario' => 100,
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
    ]);

    $comprobanteVenta = Comprobante::create([
        'tipo_operacion' => Comprobante::TIPO_OPERACION_VENTA,
        'cliente_id' => $cliente->id,
        'emisor_ruc' => '20600000001',
        'receptor_ruc' => $cliente->ruc,
        'tipo_comprobante' => 'factura',
        'serie' => 'F002',
        'numero' => '800',
        'moneda_id' => $moneda->id,
        'total' => 118,
        'estado' => Comprobante::ESTADO_REGISTRADO,
        'creado_por' => $contabilidad->id,
    ]);

    return compact(
        'moneda',
        'contabilidad',
        'proveedor',
        'cliente',
        'producto',
        'compra',
        'compraItem',
        'comprobanteCompra',
        'comprobanteVenta'
    );
}
