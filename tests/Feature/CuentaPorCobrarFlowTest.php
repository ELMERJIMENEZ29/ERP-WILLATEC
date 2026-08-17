<?php

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Comprobante;
use App\Models\Cotizacion;
use App\Models\CuentaPorCobrar;
use App\Models\EstadoCotizacion;
use App\Models\Moneda;
use App\Models\OcRecibida;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('genera cuenta por cobrar desde comprobante de venta y sincroniza oc', function () {
    $base = crearBaseCuentaPorCobrarFase8();

    Sanctum::actingAs($base['contabilidad']);

    $response = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteVenta']->id}/cuenta-por-cobrar", [
        'fecha_vencimiento' => '2026-09-13',
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorCobrar::ESTADO_PENDIENTE)
        ->assertJsonPath('total', '236.00')
        ->assertJsonPath('saldo', '236.00');

    $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteVenta']->id}/cuenta-por-cobrar", [
        'fecha_vencimiento' => '2026-09-13',
    ])
        ->assertCreated()
        ->assertJsonPath('id', $response->json('id'));

    expect(CuentaPorCobrar::query()->count())->toBe(1);
    expect($base['oc']->fresh()->estado_financiero)->toBe(OcRecibida::ESTADO_FINANCIERO_PENDIENTE);
});

test('no genera cuenta por cobrar desde comprobante de compra', function () {
    $base = crearBaseCuentaPorCobrarFase8();

    Sanctum::actingAs($base['contabilidad']);

    $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteCompra']->id}/cuenta-por-cobrar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comprobante_id');
});

test('permite cobros parciales bloquea sobrecobro y marca oc cobrada', function () {
    $base = crearBaseCuentaPorCobrarFase8();

    Sanctum::actingAs($base['contabilidad']);

    $cuentaId = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteVenta']->id}/cuenta-por-cobrar")
        ->assertCreated()
        ->json('id');

    $this->postJson("/api/contabilidad/cuentas-por-cobrar/{$cuentaId}/cobros", [
        'monto' => 100,
        'referencia' => 'COB-001',
        'idempotency_key' => 'cobro-cxc-1',
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorCobrar::ESTADO_PARCIAL)
        ->assertJsonPath('monto_cobrado', '100.00')
        ->assertJsonPath('saldo', '136.00');

    expect($base['oc']->fresh()->estado_financiero)->toBe('parcial');

    $this->postJson("/api/contabilidad/cuentas-por-cobrar/{$cuentaId}/cobros", [
        'monto' => 137,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('monto');

    $this->postJson("/api/contabilidad/cuentas-por-cobrar/{$cuentaId}/cobros", [
        'monto' => 136,
    ])
        ->assertCreated()
        ->assertJsonPath('estado', CuentaPorCobrar::ESTADO_COBRADA)
        ->assertJsonPath('saldo', '0.00');

    expect($base['oc']->fresh()->estado_financiero)->toBe('cobrado');
});

test('cobro con misma idempotency key no duplica saldo', function () {
    $base = crearBaseCuentaPorCobrarFase8();

    Sanctum::actingAs($base['contabilidad']);

    $cuentaId = $this->postJson("/api/contabilidad/comprobantes/{$base['comprobanteVenta']->id}/cuenta-por-cobrar")
        ->assertCreated()
        ->json('id');

    $payload = [
        'monto' => 40,
        'idempotency_key' => 'cobro-reintento-1',
    ];

    $this->postJson("/api/contabilidad/cuentas-por-cobrar/{$cuentaId}/cobros", $payload)
        ->assertCreated()
        ->assertJsonPath('monto_cobrado', '40.00');

    $this->postJson("/api/contabilidad/cuentas-por-cobrar/{$cuentaId}/cobros", $payload)
        ->assertCreated()
        ->assertJsonPath('monto_cobrado', '40.00');

    expect(Cobro::query()->count())->toBe(1);
});

test('ventas no puede acceder a cuentas por cobrar', function () {
    crearBaseCuentaPorCobrarFase8();

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    Sanctum::actingAs($ventas);

    $this->getJson('/api/contabilidad/cuentas-por-cobrar')
        ->assertForbidden();
});

function crearBaseCuentaPorCobrarFase8(): array
{
    test()->seed(RoleSeeder::class);

    $moneda = Moneda::create([
        'codigo' => 'PEN',
        'simbolo' => 'S/',
    ]);

    $contabilidad = User::factory()->create();
    $contabilidad->assignRole('contabilidad');

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    $tipoCliente = TipoCliente::create([
        'nombre' => 'Activo',
    ]);

    $cliente = Cliente::create([
        'nombre' => 'Cliente CxC',
        'ruc' => '20888888888',
        'correo' => 'cliente-cxc@test.local',
        'estado' => 'activo',
        'tipo_cliente_id' => $tipoCliente->id,
        'moneda_id' => $moneda->id,
    ]);

    $estadoAprobada = EstadoCotizacion::create([
        'nombre' => 'aprobada',
    ]);

    $plantilla = Plantilla::create([
        'nombre' => 'WILLATEC SOLES',
        'incluye_igv' => false,
        'formato_pdf' => 'willatec-soles',
        'activo' => true,
    ]);

    $plataforma = Plataforma::create([
        'nombre' => 'correo',
    ]);

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-CXC-001',
        'fecha' => '2026-08-13',
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 1,
        'titulo' => 'Cotizacion CxC',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'subtotal' => 200,
        'igv' => 36,
        'total' => 236,
        'ganancia' => 20,
        'total_gasto' => 180,
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

    $oc = OcRecibida::create([
        'numero' => 'OCR-CXC-001',
        'fecha_recepcion' => '2026-08-13',
        'estado' => OcRecibida::ESTADO_ATENDIDO,
        'estado_comercial' => OcRecibida::ESTADO_COMERCIAL_CERRADA,
        'estado_logistico' => OcRecibida::ESTADO_LOGISTICO_ENTREGADO,
        'estado_documental' => OcRecibida::ESTADO_DOCUMENTAL_COMPLETO,
        'estado_financiero' => OcRecibida::ESTADO_FINANCIERO_PENDIENTE,
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'cliente_correo' => $cliente->correo,
        'cotizacion_id' => $cotizacion->id,
        'cliente_id' => $cliente->id,
        'user_id' => $ventas->id,
    ]);

    $comprobanteVenta = Comprobante::create([
        'tipo_operacion' => Comprobante::TIPO_OPERACION_VENTA,
        'oc_recibida_id' => $oc->id,
        'cotizacion_id' => $cotizacion->id,
        'cliente_id' => $cliente->id,
        'emisor_ruc' => '20600000001',
        'receptor_ruc' => $cliente->ruc,
        'tipo_comprobante' => 'factura',
        'serie' => 'F002',
        'numero' => '910',
        'moneda_id' => $moneda->id,
        'subtotal' => 200,
        'igv' => 36,
        'total' => 236,
        'estado' => Comprobante::ESTADO_REGISTRADO,
        'creado_por' => $contabilidad->id,
    ]);

    $comprobanteCompra = Comprobante::create([
        'tipo_operacion' => Comprobante::TIPO_OPERACION_COMPRA,
        'emisor_ruc' => '20111111111',
        'tipo_comprobante' => 'factura',
        'serie' => 'F001',
        'numero' => '901',
        'moneda_id' => $moneda->id,
        'total' => 118,
        'estado' => Comprobante::ESTADO_REGISTRADO,
        'creado_por' => $contabilidad->id,
    ]);

    return compact(
        'moneda',
        'contabilidad',
        'ventas',
        'cliente',
        'cotizacion',
        'oc',
        'comprobanteVenta',
        'comprobanteCompra'
    );
}
