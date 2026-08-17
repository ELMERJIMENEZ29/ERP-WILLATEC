<?php

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\InventarioMovimiento;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Models\ProductoSerie;
use App\Models\Proveedor;
use App\Models\RecepcionCompra;
use App\Models\RecepcionItem;
use App\Models\RequerimientoCompra;
use App\Models\RequerimientoCompraItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('recepcion parcial confirmada genera entrada kardex y actualiza cantidades', function () {
    $base = crearBaseRecepcionCompra();
    Sanctum::actingAs($base['logistica']);

    $response = $this->postJson("/api/compras/{$base['compra']->id}/recepciones", [
        'fecha_recepcion' => '2026-08-13',
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 4,
            'costo_unitario_provisional' => 120,
            'moneda_id' => $base['moneda']->id,
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('estado', RecepcionCompra::ESTADO_BORRADOR);

    expect((float) $base['producto']->fresh()->stock_actual)->toBe(0.0);
    expect(InventarioMovimiento::query()->count())->toBe(0);

    $recepcionId = $response->json('id');

    $this->patchJson("/api/recepciones-compra/{$recepcionId}/confirmar")
        ->assertOk()
        ->assertJsonPath('estado', RecepcionCompra::ESTADO_CONFIRMADA)
        ->assertJsonPath('items.0.estado', RecepcionItem::ESTADO_CONFIRMADO);

    $productoActualizado = $base['producto']->fresh();
    expect((float) $productoActualizado->stock_actual)->toBe(4.0);
    expect((float) $productoActualizado->stock_disponible)->toBe(4.0);
    expect((float) $base['compraItem']->fresh()->cantidad_recibida)->toBe(4.0);
    expect($base['compra']->fresh()->estado)->toBe(Compra::ESTADO_PARCIALMENTE_RECIBIDA);
    expect((float) $base['requerimientoItem']->fresh()->cantidad_recibida)->toBe(4.0);
    expect(InventarioMovimiento::query()->where('referencia_tipo', 'recepcion_compra')->count())->toBe(1);
    expect(InventarioMovimiento::query()->first()->costo_tipo)->toBe('provisional');

    $this->patchJson("/api/recepciones-compra/{$recepcionId}/confirmar")
        ->assertOk();

    expect((float) $base['producto']->fresh()->stock_actual)->toBe(4.0);
    expect(InventarioMovimiento::query()->where('referencia_tipo', 'recepcion_compra')->count())->toBe(1);
});

test('permite completar compra en varias recepciones y bloquea sobrerecepcion', function () {
    $base = crearBaseRecepcionCompra();
    Sanctum::actingAs($base['logistica']);

    $primera = $this->postJson("/api/compras/{$base['compra']->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 6,
            'costo_unitario_provisional' => 100,
        ]],
    ])->assertCreated();

    $this->patchJson("/api/recepciones-compra/{$primera->json('id')}/confirmar")
        ->assertOk();

    $this->postJson("/api/compras/{$base['compra']->fresh()->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 5,
            'costo_unitario_provisional' => 100,
        ]],
    ])->assertUnprocessable();

    $segunda = $this->postJson("/api/compras/{$base['compra']->fresh()->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 4,
            'costo_unitario_provisional' => 100,
        ]],
    ])->assertCreated();

    $this->patchJson("/api/recepciones-compra/{$segunda->json('id')}/confirmar")
        ->assertOk();

    expect((float) $base['producto']->fresh()->stock_actual)->toBe(10.0);
    expect((float) $base['compraItem']->fresh()->cantidad_recibida)->toBe(10.0);
    expect($base['compra']->fresh()->estado)->toBe(Compra::ESTADO_RECIBIDA);
    expect((float) $base['requerimientoItem']->fresh()->cantidad_recibida)->toBe(10.0);
});

test('recepcion con series crea series disponibles y evita duplicados', function () {
    $base = crearBaseRecepcionCompra();
    Sanctum::actingAs($base['logistica']);

    $recepcion = $this->postJson("/api/compras/{$base['compra']->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 2,
            'costo_unitario_provisional' => 150,
        ]],
    ])->assertCreated();

    $recepcionItemId = $recepcion->json('items.0.id');

    $this->patchJson("/api/recepciones-compra/{$recepcion->json('id')}/confirmar", [
        'items' => [[
            'recepcion_item_id' => $recepcionItemId,
            'series' => ['SER-RC-001', 'SER-RC-002'],
        ]],
    ])->assertOk();

    expect(ProductoSerie::query()->where('producto_id', $base['producto']->id)->count())->toBe(2);
    expect(ProductoSerie::query()->where('serie', 'SER-RC-001')->first()->estado)->toBe(ProductoSerie::ESTADO_DISPONIBLE);

    $otraCompra = crearCompraConfirmadaRecepcion($base, cantidad: 1);

    $otraRecepcion = $this->postJson("/api/compras/{$otraCompra->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $otraCompra->items()->first()->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 1,
            'costo_unitario_provisional' => 150,
        ]],
    ])->assertCreated();

    $this->patchJson("/api/recepciones-compra/{$otraRecepcion->json('id')}/confirmar", [
        'items' => [[
            'recepcion_item_id' => $otraRecepcion->json('items.0.id'),
            'series' => ['SER-RC-001'],
        ]],
    ])->assertUnprocessable();
});

test('contabilidad solo consulta recepciones y ventas no accede', function () {
    $base = crearBaseRecepcionCompra();

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');
    Sanctum::actingAs($contabilidad);

    $this->getJson('/api/recepciones-compra')->assertOk();
    $this->postJson("/api/compras/{$base['compra']->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $base['compraItem']->id,
            'producto_id' => $base['producto']->id,
            'cantidad' => 1,
        ]],
    ])->assertForbidden();

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');
    Sanctum::actingAs($ventas);

    $this->getJson('/api/recepciones-compra')->assertForbidden();
});

test('recepcion de compra externa crea producto interno y enlaza el externo', function () {
    $base = crearBaseRecepcionCompra();
    Sanctum::actingAs($base['logistica']);

    $externo = ProductoExterno::create([
        'descripcion' => 'Docking externo especial',
        'codigo' => 'DOCK-EXT-001',
        'marca' => 'Demo',
        'unidad_medida' => 'UND',
        'costo_base_referencial' => 80,
        'moneda_id' => $base['moneda']->id,
        'activo' => true,
        'fingerprint' => ProductoExterno::fingerprintFrom([
            'descripcion' => 'Docking externo especial',
            'codigo' => 'DOCK-EXT-001',
            'marca' => 'Demo',
        ]),
    ]);

    $compra = Compra::create([
        'numero' => 'CMP-REC-EXT-001',
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => Compra::MODALIDAD_DIRECTA,
        'estado' => Compra::ESTADO_CONFIRMADA,
        'fecha_compra' => '2026-08-13',
        'moneda_id' => $base['moneda']->id,
        'creado_por' => $base['logistica']->id,
    ]);

    $compraItem = CompraItem::create([
        'compra_id' => $compra->id,
        'producto_externo_id' => $externo->id,
        'descripcion' => $externo->descripcion,
        'cantidad' => 3,
        'cantidad_recibida' => 0,
        'costo_unitario_estimado' => 80,
        'moneda_id' => $base['moneda']->id,
        'estado' => CompraItem::ESTADO_PENDIENTE,
    ]);

    $recepcion = $this->postJson("/api/compras/{$compra->id}/recepciones", [
        'items' => [[
            'compra_item_id' => $compraItem->id,
            'cantidad' => 3,
            'costo_unitario_provisional' => 80,
        ]],
    ])->assertCreated();

    $productoId = $recepcion->json('items.0.producto_id');
    expect($productoId)->not->toBeNull();
    expect($externo->fresh()->producto_id)->toBe($productoId);
    expect($compraItem->fresh()->producto_id)->toBe($productoId);

    $this->patchJson("/api/recepciones-compra/{$recepcion->json('id')}/confirmar")
        ->assertOk();

    expect((float) Producto::findOrFail($productoId)->stock_actual)->toBe(3.0);
});

/**
 * @return array<string, mixed>
 */
function crearBaseRecepcionCompra(): array
{
    test()->seed(RoleSeeder::class);

    $moneda = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);

    $logistica = User::factory()->create();
    $logistica->assignRole('logistica');

    $proveedor = Proveedor::create([
        'nombre' => 'Proveedor Recepcion',
        'ruc' => '20999999991',
        'activo' => true,
    ]);

    $producto = Producto::create([
        'nombre' => 'Equipo Recepcion',
        'sku' => 'REC-001',
        'codigo' => 'REC-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 0,
        'stock_reservado' => 0,
        'stock_disponible' => 0,
        'stock' => 0,
        'activo' => true,
        'costo_unitario' => 0,
        'costo_promedio' => 0,
        'valor_stock' => 0,
        'moneda_id' => $moneda->id,
    ]);

    $requerimiento = RequerimientoCompra::create([
        'numero' => 'REQ-REC-001',
        'origen_tipo' => 'manual',
        'estado' => RequerimientoCompra::ESTADO_COMPRADO,
        'prioridad' => 'normal',
        'solicitado_por' => $logistica->id,
    ]);

    $requerimientoItem = RequerimientoCompraItem::create([
        'requerimiento_compra_id' => $requerimiento->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad_requerida' => 10,
        'cantidad_comprada' => 10,
        'cantidad_recibida' => 0,
        'estado' => RequerimientoCompra::ESTADO_COMPRADO,
    ]);

    $compra = Compra::create([
        'numero' => 'CMP-REC-001',
        'proveedor_id' => $proveedor->id,
        'modalidad' => Compra::MODALIDAD_DIRECTA,
        'estado' => Compra::ESTADO_CONFIRMADA,
        'fecha_compra' => '2026-08-13',
        'moneda_id' => $moneda->id,
        'subtotal_estimado' => 1000,
        'total_estimado' => 1000,
        'creado_por' => $logistica->id,
    ]);

    $compraItem = CompraItem::create([
        'compra_id' => $compra->id,
        'requerimiento_compra_item_id' => $requerimientoItem->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad' => 10,
        'cantidad_recibida' => 0,
        'costo_unitario_estimado' => 100,
        'moneda_id' => $moneda->id,
        'estado' => CompraItem::ESTADO_PENDIENTE,
    ]);

    return compact('moneda', 'logistica', 'proveedor', 'producto', 'requerimiento', 'requerimientoItem', 'compra', 'compraItem');
}

function crearCompraConfirmadaRecepcion(array $base, float $cantidad): Compra
{
    $compra = Compra::create([
        'numero' => 'CMP-REC-EXTRA-'.uniqid(),
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => Compra::MODALIDAD_DIRECTA,
        'estado' => Compra::ESTADO_CONFIRMADA,
        'fecha_compra' => '2026-08-13',
        'moneda_id' => $base['moneda']->id,
        'creado_por' => $base['logistica']->id,
    ]);

    CompraItem::create([
        'compra_id' => $compra->id,
        'producto_id' => $base['producto']->id,
        'descripcion' => $base['producto']->nombre,
        'cantidad' => $cantidad,
        'cantidad_recibida' => 0,
        'costo_unitario_estimado' => 150,
        'moneda_id' => $base['moneda']->id,
        'estado' => CompraItem::ESTADO_PENDIENTE,
    ]);

    return $compra->refresh()->load('items');
}
