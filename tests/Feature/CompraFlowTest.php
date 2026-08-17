<?php

use App\Models\Compra;
use App\Models\InventarioMovimiento;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\RequerimientoCompra;
use App\Models\RequerimientoCompraItem;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('crea compra directa en borrador sin afectar stock kardex ni cantidad comprada', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    $stockAntes = (float) $base['producto']->fresh()->stock_actual;
    $movimientosAntes = InventarioMovimiento::query()->count();

    $response = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'oc_emitida_id' => null,
        'moneda_id' => $base['moneda']->id,
        'observacion' => 'Compra parcial de prueba',
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 6,
                'costo_unitario_estimado' => 100,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('modalidad', Compra::MODALIDAD_DIRECTA)
        ->assertJsonPath('estado', Compra::ESTADO_BORRADOR)
        ->assertJsonPath('oc_emitida_id', null)
        ->assertJsonPath('items.0.cantidad', '6.00');

    $compraId = $response->json('id');

    expect($compraId)->not->toBeNull();

    $item = $base['requerimientoItem']->fresh();
    $requerimiento = $base['requerimiento']->fresh();

    expect((float) $item->cantidad_comprada)->toBe(0.0);
    expect($requerimiento->estado)->toBe('en_gestion');

    expect((float) $base['producto']->fresh()->stock_actual)
        ->toBe($stockAntes);

    expect(InventarioMovimiento::query()->count())
        ->toBe($movimientosAntes);
});

test('confirmar compra actualiza cantidad comprada y es idempotente', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    $stockAntes = (float) $base['producto']->fresh()->stock_actual;
    $movimientosAntes = InventarioMovimiento::query()->count();

    $response = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'moneda_id' => $base['moneda']->id,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 6,
                'costo_unitario_estimado' => 100,
            ],
        ],
    ])->assertCreated();

    $compraId = $response->json('id');

    $this->patchJson("/api/compras/{$compraId}/confirmar")
        ->assertOk()
        ->assertJsonPath('estado', Compra::ESTADO_CONFIRMADA);

    $item = $base['requerimientoItem']->fresh();
    $requerimiento = $base['requerimiento']->fresh();

    expect((float) $item->cantidad_comprada)->toBe(6.0);
    expect($item->estado)->toBe('parcialmente_comprado');
    expect($requerimiento->estado)->toBe('parcialmente_comprado');

    // Confirmar nuevamente NO debe duplicar la cantidad.
    $this->patchJson("/api/compras/{$compraId}/confirmar")
        ->assertOk()
        ->assertJsonPath('estado', Compra::ESTADO_CONFIRMADA);

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(6.0);

    // Comprar no significa recibir.
    expect((float) $base['producto']->fresh()->stock_actual)
        ->toBe($stockAntes);

    expect(InventarioMovimiento::query()->count())
        ->toBe($movimientosAntes);
});

test('impide sobrecompra y permite completar requerimiento con segunda compra', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    // Primera compra: 6 de 10.
    $primera = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'moneda_id' => $base['moneda']->id,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 6,
            ],
        ],
    ])->assertCreated();

    $this->patchJson("/api/compras/{$primera->json('id')}/confirmar")
        ->assertOk();

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(6.0);

    // Intentar comprar 5 implicaría 11 de 10.
    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'moneda_id' => $base['moneda']->id,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 5,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cantidad');

    // Comprar exactamente las 4 restantes sí debe funcionar.
    $segunda = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'moneda_id' => $base['moneda']->id,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 4,
            ],
        ],
    ])->assertCreated();

    $this->patchJson("/api/compras/{$segunda->json('id')}/confirmar")
        ->assertOk();

    $item = $base['requerimientoItem']->fresh();
    $requerimiento = $base['requerimiento']->fresh();

    expect((float) $item->cantidad_comprada)->toBe(10.0);
    expect($item->estado)->toBe('comprado');
    expect($requerimiento->estado)->toBe('comprado');
});

test('borradores tambien impiden comprometer mas cantidad que la requerida', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    // Primer borrador compromete 6.
    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 6,
            ],
        ],
    ])->assertCreated();

    // Otro borrador por 5 llevaría el compromiso a 11.
    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 5,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cantidad');

    // Sigue sin ser una compra real hasta confirmación.
    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(0.0);

    expect($base['requerimiento']->fresh()->estado)
        ->toBe('en_gestion');
});

test('cancelar compra confirmada revierte cantidad comprada sin tocar stock', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    $stockAntes = (float) $base['producto']->fresh()->stock_actual;
    $movimientosAntes = InventarioMovimiento::query()->count();

    $response = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 6,
            ],
        ],
    ])->assertCreated();

    $compraId = $response->json('id');

    $this->patchJson("/api/compras/{$compraId}/confirmar")
        ->assertOk();

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(6.0);

    $this->patchJson("/api/compras/{$compraId}/cancelar")
        ->assertOk()
        ->assertJsonPath('estado', Compra::ESTADO_CANCELADA);

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(0.0);

    expect($base['requerimiento']->fresh()->estado)
        ->toBe('pendiente');

    expect((float) $base['producto']->fresh()->stock_actual)
        ->toBe($stockAntes);

    expect(InventarioMovimiento::query()->count())
        ->toBe($movimientosAntes);
});

test('una compra puede agrupar items de distintos requerimientos', function () {
    $base = crearBaseCompraFase4();

    $segundoRequerimiento = RequerimientoCompra::create([
        'numero' => 'REQ-COMPRA-002',
        'origen_tipo' => 'manual',
        'estado' => 'pendiente',
        'prioridad' => 'normal',
        'solicitado_por' => $base['logistica']->id,
        'observacion' => 'Segundo requerimiento para agrupacion',
    ]);

    $segundoItem = $segundoRequerimiento->items()->create([
        'descripcion' => 'Mouse Logitech',
        'cantidad_requerida' => 5,
        'cantidad_comprada' => 0,
        'cantidad_recibida' => 0,
        'estado' => 'pendiente',
    ]);

    Sanctum::actingAs($base['logistica']);

    $response = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'moneda_id' => $base['moneda']->id,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 10,
            ],
            [
                'requerimiento_compra_item_id' => $segundoItem->id,
                'cantidad' => 5,
            ],
        ],
    ])->assertCreated();

    $compraId = $response->json('id');

    expect($response->json('items'))->toHaveCount(2);

    $this->patchJson("/api/compras/{$compraId}/confirmar")
        ->assertOk();

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(10.0);

    expect((float) $segundoItem->fresh()->cantidad_comprada)
        ->toBe(5.0);

    expect($base['requerimiento']->fresh()->estado)
        ->toBe('comprado');

    expect($segundoRequerimiento->fresh()->estado)
        ->toBe('comprado');
});

test('modalidad oc proveedor exige oc emitida', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'oc_proveedor',
        'oc_emitida_id' => null,
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 5,
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('oc_emitida_id');

    expect(Compra::query()->count())->toBe(0);
});

test('no permite confirmar compra si el requerimiento fue cancelado despues del borrador', function () {
    $base = crearBaseCompraFase4();

    Sanctum::actingAs($base['logistica']);

    $response = $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'requerimiento_compra_item_id' => $base['requerimientoItem']->id,
                'cantidad' => 5,
            ],
        ],
    ])->assertCreated();

    $compraId = $response->json('id');

    $base['requerimiento']->forceFill([
        'estado' => 'cancelado',
    ])->save();

    $this->patchJson("/api/compras/{$compraId}/confirmar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(Compra::findOrFail($compraId)->estado)
        ->toBe(Compra::ESTADO_BORRADOR);

    expect((float) $base['requerimientoItem']->fresh()->cantidad_comprada)
        ->toBe(0.0);
});

/**
 * Base mínima para probar Fase 4.
 *
 * @return array<string, mixed>
 */
function crearBaseCompraFase4(): array
{
    test()->seed(RoleSeeder::class);

    $moneda = Moneda::create([
        'codigo' => 'PEN',
        'simbolo' => 'S/',
    ]);

    $logistica = User::factory()->create();
    $logistica->assignRole('logistica');

    $proveedor = Proveedor::create([
        'nombre' => 'Proveedor Fase 4',
        'ruc' => '20123456789',
        'contacto' => 'Compras',
        'telefono' => '999999999',
        'correo' => 'compras@proveedor.test',
        'activo' => true,
    ]);

    $producto = Producto::create([
        'nombre' => 'Laptop Fase 4',
        'sku' => 'COMPRA-001',
        'codigo' => 'COMPRA-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 4,
        'stock_reservado' => 0,
        'stock_disponible' => 4,
        'stock' => 4,
        'activo' => true,
        'costo_unitario' => 100,
        'costo_promedio' => 100,
        'valor_stock' => 400,
        'moneda_id' => $moneda->id,
    ]);

    $requerimiento = RequerimientoCompra::create([
        'numero' => 'REQ-COMPRA-001',
        'origen_tipo' => 'manual',
        'estado' => 'pendiente',
        'prioridad' => 'normal',
        'solicitado_por' => $logistica->id,
        'observacion' => 'Requerimiento base Fase 4',
    ]);

    $requerimientoItem = RequerimientoCompraItem::create([
        'requerimiento_compra_id' => $requerimiento->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad_requerida' => 10,
        'cantidad_comprada' => 0,
        'cantidad_recibida' => 0,
        'estado' => 'pendiente',
    ]);

    return compact(
        'moneda',
        'logistica',
        'proveedor',
        'producto',
        'requerimiento',
        'requerimientoItem'
    );
}
test('contabilidad puede consultar compras pero no crearlas', function () {
    $base = crearBaseCompraFase4();

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    Sanctum::actingAs($contabilidad);

    $this->getJson('/api/compras')
        ->assertOk();

    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'descripcion' => 'Producto no autorizado',
                'cantidad' => 1,
            ],
        ],
    ])->assertForbidden();
});

test('ventas no puede ingresar al modulo interno de compras', function () {
    $base = crearBaseCompraFase4();

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    Sanctum::actingAs($ventas);

    $this->getJson('/api/compras')
        ->assertForbidden();

    $this->postJson('/api/compras', [
        'proveedor_id' => $base['proveedor']->id,
        'modalidad' => 'directa',
        'items' => [
            [
                'descripcion' => 'Producto no autorizado',
                'cantidad' => 1,
            ],
        ],
    ])->assertForbidden();
});
