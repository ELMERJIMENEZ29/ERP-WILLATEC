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
use App\Models\ProductoSerie;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('logistica registra atenciones parciales con series y salida kardex idempotente', function () {
    $base = crearBaseAtencionConSeries();

    Sanctum::actingAs($base['ventas']);

    $this->postJson('/api/oc-recibidas', [
        'cotizacion_id' => $base['cotizacion']->id,
        'fecha_recepcion' => '2026-08-12',
        'items' => [[
            'cotizacion_item_id' => $base['item']->id,
            'seleccionado' => true,
            'cantidad_recibida' => 3,
        ]],
    ])->assertCreated();

    $oc = OcRecibida::with('items')->firstOrFail();
    expect((bool) $oc->items->first()->comprado)->toBeTrue();

    Sanctum::actingAs($base['logistica']);

    $atencion = $this->postJson("/api/oc-recibidas/{$oc->id}/atenciones", [
        'items' => [[
            'oc_recibida_item_id' => $oc->items->first()->id,
            'cantidad' => 2,
            'producto_serie_ids' => [$base['series'][0]->id, $base['series'][1]->id],
        ]],
    ])
        ->assertCreated()
        ->assertJsonPath('atencion.estado', 'preparando')
        ->json('atencion');

    $this->patchJson("/api/oc-atenciones/{$atencion['id']}/confirmar")
        ->assertOk()
        ->assertJsonPath('atencion.estado', 'entregado')
        ->assertJsonPath('atencion.items.0.estado', 'entregado');

    $movimientosSalida = InventarioMovimiento::query()
        ->where('referencia_tipo', 'oc_atencion')
        ->where('referencia_id', $atencion['id'])
        ->count();

    expect($movimientosSalida)->toBe(1);
    expect($base['producto']->refresh()->stock_actual)->toBe('1.00');
    expect($base['producto']->stock_reservado)->toBe('1.00');
    expect($base['producto']->stock_disponible)->toBe('0.00');
    expect($oc->refresh()->estado)->toBe('por_entrega');
    expect($oc->estado_logistico)->toBe('parcial');
    expect((bool) $oc->items()->first()->entregado)->toBeFalse();

    $this->patchJson("/api/oc-atenciones/{$atencion['id']}/confirmar")
        ->assertOk()
        ->assertJsonPath('atencion.estado', 'entregado');

    expect(InventarioMovimiento::query()
        ->where('referencia_tipo', 'oc_atencion')
        ->where('referencia_id', $atencion['id'])
        ->count())->toBe(1);

    $this->postJson("/api/oc-recibidas/{$oc->id}/atenciones", [
        'items' => [[
            'oc_recibida_item_id' => $oc->items()->first()->id,
            'cantidad' => 1,
            'producto_serie_ids' => [$base['series'][0]->id],
        ]],
    ])->assertUnprocessable();

    $segunda = $this->postJson("/api/oc-recibidas/{$oc->id}/atenciones", [
        'items' => [[
            'oc_recibida_item_id' => $oc->items()->first()->id,
            'cantidad' => 1,
            'producto_serie_ids' => [$base['series'][2]->id],
        ]],
    ])
        ->assertCreated()
        ->json('atencion');

    $this->patchJson("/api/oc-atenciones/{$segunda['id']}/confirmar")
        ->assertOk()
        ->assertJsonPath('atencion.estado', 'entregado');

    expect($base['producto']->refresh()->stock_actual)->toBe('0.00');
    expect($base['producto']->stock_reservado)->toBe('0.00');
    expect($oc->refresh()->estado)->toBe('atendido');
    expect($oc->estado_logistico)->toBe('entregado');
    expect((bool) $oc->items()->first()->entregado)->toBeTrue();
});

/**
 * @return array<string, mixed>
 */
function crearBaseAtencionConSeries(): array
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
        'nombre' => 'Cliente Atencion',
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
        'nombre' => 'Laptop serializada',
        'sku' => 'SER-001',
        'codigo' => 'SER-001',
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

    $series = collect(['SER-A', 'SER-B', 'SER-C'])
        ->map(fn (string $serie) => ProductoSerie::create([
            'producto_id' => $producto->id,
            'serie' => $serie,
            'estado' => ProductoSerie::ESTADO_DISPONIBLE,
            'fecha_ingreso' => '2026-08-01',
            'moneda_id' => $moneda->id,
            'costo_unitario' => 100,
        ]))
        ->values();

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-AT-001',
        'fecha' => '2026-08-12',
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 1,
        'titulo' => 'Cotizacion atencion',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'subtotal' => 300,
        'igv' => 54,
        'total' => 354,
        'ganancia' => 50,
        'total_gasto' => 250,
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

    $item = CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'producto_id' => $producto->id,
        'descripcion' => $producto->nombre,
        'cantidad' => 3,
        'codigo' => $producto->codigo,
        'unidad_medida' => 'UND',
        'costo_base' => 100,
        'costo_unitario' => 100,
        'margen' => 20,
        'precio_venta' => 140,
        'subtotal' => 420,
        'costo_total' => 300,
        'ganancia' => 120,
        'orden' => 1,
        'tipo' => 'producto',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);

    return [
        'ventas' => $ventas,
        'logistica' => $logistica,
        'producto' => $producto,
        'series' => $series,
        'cotizacion' => $cotizacion,
        'item' => $item,
    ];
}
