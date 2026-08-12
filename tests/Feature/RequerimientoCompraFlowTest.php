<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Models\InventarioMovimiento;
use App\Models\Moneda;
use App\Models\OcRecibida;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Models\OcAtencion;
use App\Models\OcAtencionItem;
use App\Models\RequerimientoCompra;
use App\Services\OcAtencionService;
use App\Models\TipoCliente;
use App\Models\User;
use App\Services\InventarioService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('genera requerimiento solo por faltante real y no duplica por doble request', function () {
    $base = crearBaseRequerimientoCompra();

    Sanctum::actingAs($base['ventas']);

    $this->postJson('/api/oc-recibidas', [
        'cotizacion_id' => $base['cotizacion']->id,
        'fecha_recepcion' => '2026-08-12',
        'items' => [
            [
                'cotizacion_item_id' => $base['itemInterno']->id,
                'seleccionado' => true,
                'cantidad_recibida' => 10,
            ],
            [
                'cotizacion_item_id' => $base['itemExterno']->id,
                'seleccionado' => true,
                'cantidad_recibida' => 2,
            ],
        ],
    ])->assertCreated();

    $oc = OcRecibida::with('items.cotizacionItem')->firstOrFail();
    $itemInternoOc = $oc->items->firstWhere('cotizacion_item_id', $base['itemInterno']->id);

    app(InventarioService::class)->reservarStock(
        productoId: $base['producto']->id,
        cantidad: 4,
        referenciaTipo: 'oc_recibida',
        referenciaId: $oc->id,
        origen: 'orden_compra',
        idempotencyKey: "oc-recibida:{$oc->id}:reserva:cotizacion-item:{$itemInternoOc->cotizacion_item_id}",
        createdBy: $base['logistica']->id,
        observacion: 'Reserva parcial simulada para faltante real'
    );

    Sanctum::actingAs($base['logistica']);

    $this->getJson("/api/oc-recibidas/{$oc->id}/requerimientos/faltantes")
        ->assertOk()
        ->assertJsonPath('faltantes.0.cantidad_solicitada', 10)
        ->assertJsonPath('faltantes.0.cantidad_cubierta', 4)
        ->assertJsonPath('faltantes.0.cantidad_faltante', 6)
        ->assertJsonPath('faltantes.1.cantidad_solicitada', 2)
        ->assertJsonPath('faltantes.1.cantidad_faltante', 2);

    $response = $this->postJson("/api/oc-recibidas/{$oc->id}/requerimientos/generar", [
        'prioridad' => 'alta',
        'observacion' => 'Comprar faltantes',
    ])
        ->assertCreated()
        ->assertJsonPath('requerimiento.origen_tipo', 'oc_cliente')
        ->assertJsonPath('requerimiento.items.0.cantidad_requerida', '6.00')
        ->assertJsonPath('requerimiento.items.1.cantidad_requerida', '2.00');

    $requerimientoId = $response->json('requerimiento.id');

    $this->postJson("/api/oc-recibidas/{$oc->id}/requerimientos/generar", [
        'prioridad' => 'alta',
    ])
        ->assertCreated()
        ->assertJsonPath('requerimiento.id', $requerimientoId);

    expect(RequerimientoCompra::query()->count())->toBe(1);
    expect(InventarioMovimiento::query()
        ->where('tipo_movimiento', InventarioMovimiento::TIPO_ENTRADA)
        ->orWhere('tipo_movimiento', InventarioMovimiento::TIPO_SALIDA)
        ->count())->toBe(0);
});

test('permite requerimiento manual sin oc recibida', function () {
    test()->seed(RoleSeeder::class);
    $logistica = User::factory()->create();
    $logistica->assignRole('logistica');
    Sanctum::actingAs($logistica);

    $this->postJson('/api/requerimientos-compra', [
        'origen_tipo' => 'manual',
        'prioridad' => 'normal',
        'items' => [[
            'descripcion' => 'Producto especial sin catalogo',
            'cantidad_requerida' => 3,
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('requerimiento.oc_recibida_id', null)
        ->assertJsonPath('requerimiento.items.0.descripcion', 'Producto especial sin catalogo')
        ->assertJsonPath('requerimiento.items.0.cantidad_requerida', '3.00');
});

test('descuenta atenciones confirmadas al calcular faltantes de oc', function () {
    $base = crearBaseRequerimientoCompra();

    $base['producto']->forceFill([
        'stock_actual' => 10,
        'stock_disponible' => 10,
        'stock' => 10,
        'valor_stock' => 1000,
    ])->save();

    Sanctum::actingAs($base['ventas']);

    $this->postJson('/api/oc-recibidas', [
        'cotizacion_id' => $base['cotizacion']->id,
        'fecha_recepcion' => '2026-08-12',
        'items' => [[
            'cotizacion_item_id' => $base['itemInterno']->id,
            'seleccionado' => true,
            'cantidad_recibida' => 10,
        ]],
    ])->assertCreated();

    $oc = OcRecibida::with('items.cotizacionItem')->firstOrFail();
    $itemOc = $oc->items->firstWhere('cotizacion_item_id', $base['itemInterno']->id);

    app(InventarioService::class)->reservarStock(
        productoId: $base['producto']->id,
        cantidad: 4,
        referenciaTipo: 'oc_recibida',
        referenciaId: $oc->id,
        origen: 'orden_compra',
        idempotencyKey: "oc-recibida:{$oc->id}:reserva:cotizacion-item:{$itemOc->cotizacion_item_id}",
        createdBy: $base['logistica']->id,
        observacion: 'Reserva para atencion parcial'
    );

    $atencion = OcAtencion::create([
        'oc_recibida_id' => $oc->id,
        'numero' => 'AT-TEST-001',
        'fecha_atencion' => now(),
        'estado' => OcAtencion::ESTADO_PREPARANDO,
        'tipo_atencion' => 'entrega_cliente',
        'preparado_por' => $base['logistica']->id,
        'created_by' => $base['logistica']->id,
    ]);

    OcAtencionItem::create([
        'oc_atencion_id' => $atencion->id,
        'oc_recibida_item_id' => $itemOc->id,
        'producto_id' => $base['producto']->id,
        'descripcion' => $itemOc->descripcion,
        'codigo' => $itemOc->codigo,
        'marca' => $itemOc->marca,
        'unidad_medida' => $itemOc->unidad_medida,
        'cantidad' => 4,
        'cantidad_entregada' => 0,
        'estado' => OcAtencionItem::ESTADO_PENDIENTE,
    ]);

    Sanctum::actingAs($base['logistica']);

    app(OcAtencionService::class)->confirmar($atencion, [], request());

    RequerimientoCompra::create([
        'numero' => 'REQ-ACT-001',
        'origen_tipo' => 'oc_cliente',
        'oc_recibida_id' => $oc->id,
        'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
        'prioridad' => RequerimientoCompra::PRIORIDAD_NORMAL,
        'solicitado_por' => $base['logistica']->id,
    ])->items()->create([
        'oc_recibida_item_id' => $itemOc->id,
        'cotizacion_item_id' => $itemOc->cotizacion_item_id,
        'producto_id' => $base['producto']->id,
        'descripcion' => $itemOc->descripcion,
        'cantidad_requerida' => 6,
        'cantidad_comprada' => 0,
        'cantidad_recibida' => 0,
        'estado' => RequerimientoCompra::ESTADO_PENDIENTE,
    ]);

    $this->getJson("/api/oc-recibidas/{$oc->id}/requerimientos/faltantes")
        ->assertOk()
        ->assertJsonPath('faltantes', []);
});

/**
 * @return array<string, mixed>
 */
function crearBaseRequerimientoCompra(): array
{
    test()->seed(RoleSeeder::class);

    $estadoAprobada = EstadoCotizacion::create(['nombre' => 'aprobada']);
    $estadoItem = EstadoCotizacionItem::create(['nombre' => 'pendiente']);
    $moneda = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);
    $plantilla = Plantilla::create([
        'nombre' => 'WILLATEC SOLES',
        'incluye_igv' => false,
        'formato_pdf' => 'willatec-soles',
        'activo' => true,
    ]);
    $plataforma = Plataforma::create(['nombre' => 'correo']);
    $tipoCliente = TipoCliente::create(['nombre' => 'Activo']);
    $cliente = Cliente::create([
        'nombre' => 'Cliente Requerimiento',
        'ruc' => '12345678901',
        'correo' => 'cliente@example.com',
        'tipo_cliente_id' => $tipoCliente->id,
        'plantilla_id' => $plantilla->id,
    ]);

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    $logistica = User::factory()->create();
    $logistica->assignRole('logistica');

    $producto = Producto::create([
        'nombre' => 'Laptop parcial',
        'sku' => 'REQ-INT-001',
        'codigo' => 'REQ-INT-001',
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

    $productoExterno = ProductoExterno::create([
        'descripcion' => 'Monitor externo especial',
        'codigo' => 'REQ-EXT-001',
        'marca' => 'Demo',
        'unidad_medida' => 'UND',
        'costo_base_referencial' => 50,
        'moneda_id' => $moneda->id,
        'activo' => true,
        'fingerprint' => ProductoExterno::fingerprintFrom([
            'descripcion' => 'Monitor externo especial',
            'codigo' => 'REQ-EXT-001',
            'marca' => 'Demo',
            'proveedor' => null,
        ]),
    ]);

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-REQ-001',
        'fecha' => '2026-08-12',
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 1,
        'titulo' => 'Cotizacion requerimiento',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'subtotal' => 1000,
        'igv' => 180,
        'total' => 1180,
        'ganancia' => 100,
        'total_gasto' => 900,
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'estado_cotizacion_id' => $estadoAprobada->id,
        'user_id' => $ventas->id,
        'plataforma_id' => $plataforma->id,
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'cliente_contacto' => 'Compras',
        'cliente_correo' => $cliente->correo,
    ]);

    $itemInterno = CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad' => 10,
        'codigo' => $producto->codigo,
        'unidad_medida' => 'UND',
        'costo_base' => 100,
        'costo_unitario' => 100,
        'margen' => 20,
        'precio_venta' => 140,
        'subtotal' => 1400,
        'costo_total' => 1000,
        'ganancia' => 400,
        'orden' => 1,
        'tipo' => 'producto',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);

    $itemExterno = CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'producto_externo_id' => $productoExterno->id,
        'descripcion' => $productoExterno->descripcion,
        'cantidad' => 2,
        'codigo' => $productoExterno->codigo,
        'unidad_medida' => 'UND',
        'costo_base' => 50,
        'costo_unitario' => 50,
        'margen' => 20,
        'precio_venta' => 80,
        'subtotal' => 160,
        'costo_total' => 100,
        'ganancia' => 60,
        'orden' => 2,
        'tipo' => 'producto_externo',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);

    return compact('ventas', 'logistica', 'producto', 'productoExterno', 'cotizacion', 'itemInterno', 'itemExterno');
}
