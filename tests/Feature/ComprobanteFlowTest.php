<?php

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Comprobante;
use App\Models\InventarioMovimiento;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('registra comprobante de compra sin afectar stock ni kardex', function () {
    $base = crearBaseComprobanteFase6();

    Sanctum::actingAs($base['contabilidad']);

    $stockAntes = (float) $base['producto']->fresh()->stock_actual;
    $movimientosAntes = InventarioMovimiento::query()->count();

    $response = $this->postJson('/api/contabilidad/comprobantes', [
        'tipo_operacion' => Comprobante::TIPO_OPERACION_COMPRA,
        'compra_id' => $base['compra']->id,
        'proveedor_id' => $base['proveedor']->id,
        'emisor_ruc' => $base['proveedor']->ruc,
        'emisor_nombre' => $base['proveedor']->nombre,
        'receptor_ruc' => '20600000001',
        'receptor_nombre' => 'Willatec S.A.C.',
        'tipo_comprobante' => 'factura',
        'serie' => 'F001',
        'numero' => '123',
        'fecha_emision' => '2026-08-13',
        'moneda_id' => $base['moneda']->id,
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
        'items' => [
            [
                'compra_item_id' => $base['compraItem']->id,
                'producto_id' => $base['producto']->id,
                'descripcion' => 'Laptop comprobante',
                'cantidad' => 1,
                'valor_unitario' => 100,
                'subtotal' => 100,
                'igv' => 18,
                'total' => 118,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('tipo_operacion', Comprobante::TIPO_OPERACION_COMPRA)
        ->assertJsonPath('estado', Comprobante::ESTADO_REGISTRADO)
        ->assertJsonPath('items.0.descripcion', 'Laptop comprobante');

    expect($response->json('id'))->not->toBeNull();
    expect((float) $base['producto']->fresh()->stock_actual)->toBe($stockAntes);
    expect(InventarioMovimiento::query()->count())->toBe($movimientosAntes);
});

test('bloquea comprobantes duplicados por emisor tipo serie y numero', function () {
    $base = crearBaseComprobanteFase6();

    Sanctum::actingAs($base['contabilidad']);

    $payload = [
        'tipo_operacion' => Comprobante::TIPO_OPERACION_COMPRA,
        'compra_id' => $base['compra']->id,
        'proveedor_id' => $base['proveedor']->id,
        'emisor_ruc' => $base['proveedor']->ruc,
        'tipo_comprobante' => 'factura',
        'serie' => 'F001',
        'numero' => '124',
        'total' => 50,
        'items' => [
            [
                'descripcion' => 'Servicio duplicado',
                'cantidad' => 1,
                'total' => 50,
            ],
        ],
    ];

    $this->postJson('/api/contabilidad/comprobantes', $payload)
        ->assertCreated();

    $this->postJson('/api/contabilidad/comprobantes', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('numero');
});

test('permite comprobantes de venta vinculados a cliente sin crear cuentas ni inventario', function () {
    $base = crearBaseComprobanteFase6();

    Sanctum::actingAs($base['contabilidad']);

    $stockAntes = (float) $base['producto']->fresh()->stock_actual;
    $movimientosAntes = InventarioMovimiento::query()->count();

    $this->postJson('/api/contabilidad/comprobantes', [
        'tipo_operacion' => Comprobante::TIPO_OPERACION_VENTA,
        'cliente_id' => $base['cliente']->id,
        'emisor_ruc' => '20600000001',
        'emisor_nombre' => 'Willatec S.A.C.',
        'receptor_ruc' => $base['cliente']->ruc,
        'receptor_nombre' => $base['cliente']->nombre,
        'tipo_comprobante' => 'factura',
        'serie' => 'F002',
        'numero' => '900',
        'moneda_id' => $base['moneda']->id,
        'total' => 250,
        'items' => [
            [
                'producto_id' => $base['producto']->id,
                'descripcion' => 'Venta referencial',
                'cantidad' => 1,
                'valor_unitario' => 250,
                'total' => 250,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('tipo_operacion', Comprobante::TIPO_OPERACION_VENTA);

    expect((float) $base['producto']->fresh()->stock_actual)->toBe($stockAntes);
    expect(InventarioMovimiento::query()->count())->toBe($movimientosAntes);
});

test('anular comprobante es idempotente y no elimina el registro', function () {
    $base = crearBaseComprobanteFase6();

    Sanctum::actingAs($base['contabilidad']);

    $response = $this->postJson('/api/contabilidad/comprobantes', [
        'tipo_operacion' => Comprobante::TIPO_OPERACION_COMPRA,
        'compra_id' => $base['compra']->id,
        'proveedor_id' => $base['proveedor']->id,
        'emisor_ruc' => $base['proveedor']->ruc,
        'tipo_comprobante' => 'factura',
        'serie' => 'F001',
        'numero' => '125',
        'total' => 50,
        'items' => [
            [
                'descripcion' => 'Servicio para anular',
                'cantidad' => 1,
                'total' => 50,
            ],
        ],
    ])->assertCreated();

    $id = $response->json('id');

    $this->patchJson("/api/contabilidad/comprobantes/{$id}/anular")
        ->assertOk()
        ->assertJsonPath('estado', Comprobante::ESTADO_ANULADO);

    $this->patchJson("/api/contabilidad/comprobantes/{$id}/anular")
        ->assertOk()
        ->assertJsonPath('estado', Comprobante::ESTADO_ANULADO);

    expect(Comprobante::query()->count())->toBe(1);
});

test('ventas no puede acceder a comprobantes contables', function () {
    crearBaseComprobanteFase6();

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    Sanctum::actingAs($ventas);

    $this->getJson('/api/contabilidad/comprobantes')
        ->assertForbidden();
});

function crearBaseComprobanteFase6(): array
{
    test()->seed(RoleSeeder::class);

    $moneda = Moneda::create([
        'codigo' => 'PEN',
        'simbolo' => 'S/',
    ]);

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    $proveedor = Proveedor::create([
        'nombre' => 'Proveedor Comprobantes',
        'ruc' => '20123456789',
        'activo' => true,
    ]);

    $tipoCliente = TipoCliente::create([
        'nombre' => 'Activo',
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Cliente Comprobantes',
        'ruc' => '20999999999',
        'correo' => 'cliente@test.local',
        'estado' => 'activo',
        'tipo_cliente_id' => $tipoCliente->id,
        'moneda_id' => $moneda->id,
    ]);

    $producto = Producto::create([
        'nombre' => 'Laptop Comprobantes',
        'sku' => 'COMP-F6-001',
        'codigo' => 'COMP-F6-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 3,
        'stock_reservado' => 0,
        'stock_disponible' => 3,
        'stock' => 3,
        'activo' => true,
        'costo_unitario' => 100,
        'costo_promedio' => 100,
        'valor_stock' => 300,
        'moneda_id' => $moneda->id,
    ]);

    $compra = Compra::create([
        'numero' => 'CMP-F6-001',
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

    return compact(
        'moneda',
        'contabilidad',
        'proveedor',
        'cliente',
        'producto',
        'compra',
        'compraItem'
    );
}
