<?php

use App\Models\Licitacion;
use App\Models\Cotizacion;
use App\Models\CotizacionModificacion;
use App\Models\Cliente;
use App\Models\EstadoCotizacion;
use App\Models\Moneda;
use App\Models\Plantilla;
use App\Models\TipoCliente;
use App\Models\User;
use App\Notifications\OportunidadAtendidaNotification;
use App\Notifications\OportunidadComentarioNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('notifica a participantes cuando oportunidad asignada pasa a atendida', function () {
    seedRolesParaOportunidades();

    $creator = usuarioConRolOportunidad('ventas');
    $assigned = usuarioConRolOportunidad('ventas');
    $superadmin = usuarioConRolOportunidad('superadmin');
    $licitaciones = usuarioConRolOportunidad('licitacion');

    $oportunidad = Licitacion::create([
        'tipo' => 'privado',
        'empresa' => 'Cliente Notificacion',
        'requerimiento' => 'Renovacion de equipos',
        'vigencia' => now('America/Lima')->addDay(),
        'categoria' => 'Hardware',
        'estado' => 'cotizacion_generada',
        'asignado_a' => $assigned->id,
        'ejecutivo_id' => $assigned->id,
        'created_by' => $creator->id,
        'creado_por' => trim("{$creator->nombres} {$creator->apellidos}"),
        'creado_en' => now('America/Lima'),
        'modificado_en' => now('America/Lima'),
    ]);

    Sanctum::actingAs($assigned);

    $this->putJson("/api/licitaciones/{$oportunidad->id}", [
        'tipo' => 'privado',
        'empresa' => $oportunidad->empresa,
        'requerimiento' => $oportunidad->requerimiento,
        'vigencia' => $oportunidad->vigencia->toIso8601String(),
        'categoria' => $oportunidad->categoria,
        'estado' => 'atendido',
        'asignado_a' => $assigned->id,
        'ejecutivo_id' => $assigned->id,
        'presentacion_evidencia' => [
            'nombre' => 'evidencia.png',
            'tipo' => 'image/png',
            'dataUrl' => 'data:image/png;base64,AA==',
        ],
    ])->assertOk();

    foreach ([$assigned, $creator, $superadmin, $licitaciones] as $user) {
        expect(DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('type', OportunidadAtendidaNotification::class)
            ->exists())->toBeTrue();
    }
});

test('notifica comentario interno a participantes excepto al autor', function () {
    seedRolesParaOportunidades();

    $creator = usuarioConRolOportunidad('ventas');
    $assigned = usuarioConRolOportunidad('ventas');
    $superadmin = usuarioConRolOportunidad('superadmin');
    $licitaciones = usuarioConRolOportunidad('licitacion');

    $oportunidad = Licitacion::create([
        'tipo' => 'wherex',
        'empresa' => 'Entidad Comentario',
        'requerimiento' => 'Switches administrables',
        'vigencia' => now('America/Lima')->addDay(),
        'categoria' => 'Redes',
        'estado' => 'en_atencion',
        'asignado_a' => $assigned->id,
        'ejecutivo_id' => $assigned->id,
        'created_by' => $creator->id,
        'creado_por' => trim("{$creator->nombres} {$creator->apellidos}"),
        'creado_en' => now('America/Lima'),
        'modificado_en' => now('America/Lima'),
    ]);

    Sanctum::actingAs($assigned);

    $this->postJson("/api/licitaciones/{$oportunidad->id}/comentarios", [
        'comentario' => 'Validar condiciones antes de subir.',
    ])->assertCreated();

    expect(DB::table('notifications')
        ->where('notifiable_id', $assigned->id)
        ->where('type', OportunidadComentarioNotification::class)
        ->exists())->toBeFalse();

    foreach ([$creator, $superadmin, $licitaciones] as $user) {
        expect(DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('type', OportunidadComentarioNotification::class)
            ->exists())->toBeTrue();
    }
});

test('oportunidad nueva se apaga solo para el usuario que abre el detalle', function () {
    seedRolesParaOportunidades();

    $ventasUno = usuarioConRolOportunidad('ventas');
    $ventasDos = usuarioConRolOportunidad('ventas');

    $oportunidad = Licitacion::create([
        'tipo' => 'licitacion',
        'empresa' => 'Entidad Nueva',
        'requerimiento' => 'Servidores para sede central',
        'vigencia' => now('America/Lima')->addDay(),
        'categoria' => 'Servidores',
        'estado' => 'sin_atender',
        'es_nueva' => true,
        'created_by' => $ventasDos->id,
        'creado_por' => trim("{$ventasDos->nombres} {$ventasDos->apellidos}"),
        'creado_en' => now('America/Lima'),
        'modificado_en' => now('America/Lima'),
    ]);

    Sanctum::actingAs($ventasUno);

    $this->getJson('/api/licitaciones')
        ->assertOk()
        ->assertJsonPath('0.id', (string) $oportunidad->id)
        ->assertJsonPath('0.es_nueva', true);

    $this->getJson("/api/licitaciones/{$oportunidad->id}")
        ->assertOk()
        ->assertJsonPath('es_nueva', false);

    $this->getJson('/api/licitaciones')
        ->assertOk()
        ->assertJsonPath('0.es_nueva', false);

    Sanctum::actingAs($ventasDos);

    $this->getJson('/api/licitaciones')
        ->assertOk()
        ->assertJsonPath('0.es_nueva', true);
});

test('pdf de cotizacion desde oportunidad solo se permite aprobada y sin modificacion pendiente', function () {
    seedRolesParaOportunidades();

    $licitacionUser = usuarioConRolOportunidad('licitacion');
    $estadoEnviada = EstadoCotizacion::create(['nombre' => 'enviada']);
    $estadoAprobada = EstadoCotizacion::create(['nombre' => 'aprobada']);
    $tipoCliente = TipoCliente::create(['nombre' => 'Activo']);
    $moneda = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);
    $cliente = Cliente::create([
        'nombre' => 'Cliente Oportunidad',
        'ruc' => '20123456789',
        'correo' => 'cliente@example.com',
        'estado' => 'activo',
        'tipo_cliente_id' => $tipoCliente->id,
        'moneda_id' => $moneda->id,
    ]);
    $plantilla = Plantilla::create([
        'nombre' => 'Willatec Soles',
        'formato_pdf' => 'willatec-soles',
        'incluye_igv' => true,
        'moneda_id' => $moneda->id,
        'activo' => true,
    ]);

    $cotizacion = Cotizacion::create([
        'numero' => 'COT-OP-001',
        'titulo' => 'Cotizacion oportunidad',
        'fecha' => now('America/Lima')->toDateString(),
        'validez_dias' => 15,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 3.75,
        'estado_cotizacion_id' => $estadoEnviada->id,
        'subtotal' => 0,
        'igv' => 0,
        'total' => 0,
        'ganancia' => 0,
        'total_gasto' => 0,
        'cliente_id' => $cliente->id,
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'plantilla_id' => $plantilla->id,
        'moneda_id' => $moneda->id,
        'user_id' => $licitacionUser->id,
    ]);

    Sanctum::actingAs($licitacionUser);

    $this->getJson("/api/cotizaciones/{$cotizacion->id}/exportar-pdf?desde_oportunidad=1")
        ->assertUnprocessable()
        ->assertSee('aprobada');

    $cotizacion->update(['estado_cotizacion_id' => $estadoAprobada->id]);

    CotizacionModificacion::create([
        'cotizacion_id' => $cotizacion->id,
        'estado' => CotizacionModificacion::ESTADO_EN_REVISION,
        'version_number' => 2,
        'motivo' => 'Cambio pendiente',
        'propuesta' => [],
        'requested_by' => $licitacionUser->id,
    ]);

    $this->getJson("/api/cotizaciones/{$cotizacion->id}/exportar-pdf?desde_oportunidad=1")
        ->assertUnprocessable()
        ->assertSee('modificacion pendiente');
});


function seedRolesParaOportunidades(): void
{
    test()->seed(RoleSeeder::class);
}

function usuarioConRolOportunidad(string $role): User
{
    $user = User::factory()->create(['activo' => true]);
    $user->assignRole($role);

    return $user;
}
