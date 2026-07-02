<?php

use App\Models\Cliente;
use App\Models\EstadoCotizacion;
use App\Models\Moneda;
use App\Models\Plantilla;
use App\Models\TipoCliente;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('la delegacion de edicion acepta usuarios superadmin', function () {
    $this->seed(RoleSeeder::class);
    EstadoCotizacion::create(['nombre' => 'borrador']);

    $plantilla = Plantilla::create([
        'nombre' => 'WILLATEC SOLES',
        'incluye_igv' => false,
        'formato_pdf' => 'willatec-soles',
        'activo' => true,
    ]);
    $tipoCliente = TipoCliente::create(['nombre' => 'Activo']);
    $cliente = Cliente::create([
        'nombre' => 'Cliente Demo',
        'ruc' => '12345678901',
        'telefono' => '999999999',
        'correo' => 'cliente@example.com',
        'tipo_cliente_id' => $tipoCliente->id,
        'plantilla_id' => $plantilla->id,
    ]);
    $moneda = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    Sanctum::actingAs($ventas);

    $this->postJson('/api/cotizaciones', [
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'titulo' => 'Cotizacion delegada a superadmin',
        'modo_distribucion' => 'POR_ITEM',
        'moneda_id' => $moneda->id,
        'delegado_cotizacion_id' => $superadmin->id,
    ])
        ->assertCreated()
        ->assertJsonPath('cotizacion.delegado_cotizacion_id', $superadmin->id);
});
