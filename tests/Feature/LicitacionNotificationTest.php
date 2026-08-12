<?php

use App\Models\Licitacion;
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
