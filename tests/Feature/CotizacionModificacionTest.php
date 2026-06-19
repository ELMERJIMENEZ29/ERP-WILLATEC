<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Models\Moneda;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('una cotizacion aprobada se modifica mediante propuesta versionada', function () {
    $this->seed(RoleSeeder::class);

    $estadoAprobada = EstadoCotizacion::create(['nombre' => 'aprobada']);
    EstadoCotizacion::create(['nombre' => 'borrador']);
    EstadoCotizacionItem::create(['nombre' => 'pendiente']);

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

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-001',
        'fecha' => now()->toDateString(),
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 1,
        'titulo' => 'Cotizacion original',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
        'ganancia' => 10,
        'total_gasto' => 0,
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'estado_cotizacion_id' => $estadoAprobada->id,
        'user_id' => $ventas->id,
        'plataforma_id' => $plataforma->id,
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'cliente_contacto' => 'Contacto original',
        'cliente_telefono' => $cliente->telefono,
        'cliente_correo' => $cliente->correo,
    ]);

    CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Item original',
        'cantidad' => 1,
        'costo_base' => 100,
        'costo_unitario' => 100,
        'margen' => 10,
        'precio_venta' => 111.11,
        'subtotal' => 111.11,
        'costo_total' => 100,
        'ganancia' => 11.11,
        'orden' => 1,
        'tipo' => 'personalizado',
        'estado_cotizacion_item_id' => 1,
    ]);

    Sanctum::actingAs($ventas);

    $payload = [
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'plataforma_id' => $plataforma->id,
        'titulo' => 'Cotizacion editada directa',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'items' => [[
            'descripcion' => 'Item editado',
            'cantidad' => 2,
            'costo_base' => 120,
            'margen' => 15,
            'tipo' => 'personalizado',
        ]],
    ];

    $this->putJson("/api/cotizaciones/{$cotizacion->id}/completa", $payload)
        ->assertStatus(422);

    $modificacionId = $this->postJson("/api/cotizaciones/{$cotizacion->id}/solicitar-modificacion", [
        'motivo' => 'Actualizar cantidades',
    ])
        ->assertCreated()
        ->json('modificacion.id');

    $this->putJson("/api/cotizaciones/modificaciones/{$modificacionId}", [
        ...$payload,
        'titulo' => 'Cotizacion version dos',
        'items' => [[
            'descripcion' => 'Item version dos',
            'cantidad' => 2,
            'costo_base' => 120,
            'margen' => 15,
            'tipo' => 'personalizado',
        ]],
    ])->assertOk();

    Sanctum::actingAs($admin);

    $this->patchJson("/api/cotizaciones/modificaciones/{$modificacionId}/aprobar")
        ->assertOk()
        ->assertJsonPath('version.numero_version', 'COT-001 V2');

    expect($cotizacion->refresh()->titulo)->toBe('Cotizacion version dos');

    $this->assertDatabaseHas('cotizacion_versiones', [
        'cotizacion_id' => $cotizacion->id,
        'version_number' => 1,
        'numero_version' => 'COT-001 V1',
    ]);

    $this->assertDatabaseHas('cotizacion_versiones', [
        'cotizacion_id' => $cotizacion->id,
        'version_number' => 2,
        'numero_version' => 'COT-001 V2',
    ]);
});
