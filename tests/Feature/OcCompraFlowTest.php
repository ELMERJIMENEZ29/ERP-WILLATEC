<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemProveedor;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Models\Moneda;
use App\Models\OcEmitida;
use App\Models\OcRecibida;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('oc recibida solo queda atendida cuando items estan comprados entregados y documentos completos', function () {
    Storage::fake('public');
    $base = crearCotizacionBase();
    Sanctum::actingAs($base['ventas']);

    $response = $this->post('/api/oc-recibidas', [
        'cotizacion_id' => $base['cotizacion']->id,
        'fecha_recepcion' => '2026-06-20',
        'items' => [
            [
                'cotizacion_item_id' => $base['items'][0]->id,
                'seleccionado' => true,
                'cantidad_recibida' => 2,
            ],
            [
                'cotizacion_item_id' => $base['items'][1]->id,
                'seleccionado' => true,
                'cantidad_recibida' => 1,
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'OC RECIBIDA GUARDADA')
        ->assertJsonPath('cotizacion.estado', 'oc_registrada')
        ->assertJsonPath('oc_recibida.estado', 'pendiente')
        ->assertJsonPath('oc_recibida.documentos_completos', false);

    $base['superadmin']->refresh();
    expect($base['superadmin']->notifications)->toHaveCount(1);
    expect($base['superadmin']->notifications->first()->data['message'])
        ->toContain('registro una OC recibida correspondiente a la cotizacion COT-OC-001');
    expect($base['superadmin']->notifications->first()->data['registered_at'])->not->toBeNull();

    $ocRecibida = OcRecibida::with('items')->firstOrFail();

    $this->patchJson("/api/oc-recibidas/{$ocRecibida->id}/items", [
        'items' => $ocRecibida->items->map(fn ($item): array => [
            'id' => $item->id,
            'comprado' => true,
            'entregado' => true,
        ])->values()->all(),
    ])
        ->assertOk()
        ->assertJsonPath('estado', 'por_entrega')
        ->assertJsonPath('documentos_completos', false)
        ->assertJsonPath('faltantes.0', 'orden_compra_cliente')
        ->assertJsonPath('faltantes.1', 'guia_emision');

    $this->post("/api/oc-recibidas/{$ocRecibida->id}/documentos", [
        'orden_compra_cliente' => UploadedFile::fake()->create('oc.pdf', 10, 'application/pdf'),
        'guia_emision' => UploadedFile::fake()->create('guia.pdf', 10, 'application/pdf'),
    ])
        ->assertOk()
        ->assertJsonPath('estado', 'atendido')
        ->assertJsonPath('documentos_completos', true);
});

test('oc emitida se genera desde proveedor de cotizacion con totales y pdf', function () {
    Storage::fake('public');
    $base = crearCotizacionBase();
    Sanctum::actingAs($base['ventas']);

    $this->getJson("/api/cotizaciones/{$base['cotizacion']->id}/oc-emitida/preview")
        ->assertOk()
        ->assertJsonPath('proveedores.0.nombre', 'Proveedor A')
        ->assertJsonPath('proveedores.0.items_count', 1);

    $this->getJson("/api/cotizaciones/{$base['cotizacion']->id}/oc-emitida/items?proveedor=Proveedor%20A")
        ->assertOk()
        ->assertJsonPath('items.0.cotizacion_item_id', $base['items'][0]->id)
        ->assertJsonPath('totales.subtotal', 300);

    $this->postJson('/api/oc-emitidas', [
        'cotizacion_id' => $base['cotizacion']->id,
        'proveedor' => 'Proveedor A',
        'fecha_emision' => '2026-06-20',
        'items' => [
            [
                'cotizacion_item_id' => $base['items'][0]->id,
                'cantidad' => 2,
                'precio_unitario' => 150,
            ],
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'OC EMITIDA')
        ->assertJsonPath('oc_emitida.proveedor', 'Proveedor A')
        ->assertJsonPath('oc_emitida.subtotal', '300.00')
        ->assertJsonPath('oc_emitida.igv', '54.00')
        ->assertJsonPath('oc_emitida.total', '354.00')
        ->assertJsonPath('oc_emitida.documentos_completos', false);

    $base['superadmin']->refresh();
    expect($base['superadmin']->notifications)->toHaveCount(1);
    expect($base['superadmin']->notifications->first()->data['message'])
        ->toContain('emitio una OC para el proveedor Proveedor A correspondiente a la cotizacion COT-OC-001');
    expect($base['superadmin']->notifications->first()->data['issued_at'])->not->toBeNull();

    $ocEmitida = OcEmitida::firstOrFail();

    expect($ocEmitida->pdf_path)->not->toBeNull();
    Storage::disk('public')->assertExists($ocEmitida->pdf_path);
});

/**
 * @return array{ventas: User, superadmin: User, cotizacion: Cotizacion, items: array<int, CotizacionItem>}
 */
function crearCotizacionBase(): array
{
    test()->seed(RoleSeeder::class);

    $estadoAprobada = EstadoCotizacion::create(['nombre' => 'aprobada']);
    EstadoCotizacion::create(['nombre' => 'parcialmente_aprobada']);
    EstadoCotizacion::create(['nombre' => 'oc_registrada']);
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
        'nombre' => 'Cliente Demo',
        'ruc' => '12345678901',
        'telefono' => '999999999',
        'correo' => 'cliente@example.com',
        'tipo_cliente_id' => $tipoCliente->id,
        'plantilla_id' => $plantilla->id,
    ]);

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-OC-001',
        'fecha' => '2026-06-20',
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 1,
        'titulo' => 'Cotizacion con OC',
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
        'cliente_telefono' => $cliente->telefono,
        'cliente_correo' => $cliente->correo,
    ]);

    $itemA = CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Laptop Lenovo',
        'cantidad' => 2,
        'codigo' => 'LEN-1',
        'unidad_medida' => 'UND',
        'costo_base' => 120,
        'costo_unitario' => 120,
        'margen' => 20,
        'precio_venta' => 180,
        'subtotal' => 360,
        'costo_total' => 240,
        'ganancia' => 120,
        'orden' => 1,
        'tipo' => 'personalizado',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);

    $itemB = CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Mouse Logitech',
        'cantidad' => 1,
        'codigo' => 'LOG-1',
        'unidad_medida' => 'UND',
        'costo_base' => 30,
        'costo_unitario' => 30,
        'margen' => 10,
        'precio_venta' => 50,
        'subtotal' => 50,
        'costo_total' => 30,
        'ganancia' => 20,
        'orden' => 2,
        'tipo' => 'personalizado',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);

    CotizacionItemProveedor::create([
        'cotizacion_item_id' => $itemA->id,
        'nombre' => 'Proveedor A',
        'precio' => 150,
        'orden' => 1,
    ]);

    CotizacionItemProveedor::create([
        'cotizacion_item_id' => $itemB->id,
        'nombre' => 'Proveedor B',
        'precio' => 35,
        'orden' => 1,
    ]);

    return [
        'ventas' => $ventas,
        'superadmin' => $superadmin,
        'cotizacion' => $cotizacion,
        'items' => [$itemA, $itemB],
    ];
}
