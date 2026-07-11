<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
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

test('alquiler se guarda en soles y calcula precio unitario por periodo', function () {
    $this->seed(RoleSeeder::class);

    EstadoCotizacion::insert([
        ['nombre' => 'borrador'],
        ['nombre' => 'enviada'],
        ['nombre' => 'parcialmente_aprobada'],
        ['nombre' => 'aprobada'],
        ['nombre' => 'rechazada'],
    ]);
    EstadoCotizacionItem::create(['nombre' => 'pendiente']);

    $pen = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);
    $usd = Moneda::create(['codigo' => 'USD', 'simbolo' => '$']);
    $plantilla = Plantilla::create([
        'nombre' => 'ALQUILER PRIVADO',
        'incluye_igv' => false,
        'formato_pdf' => 'alquiler-privado',
        'activo' => true,
    ]);
    $plataforma = Plataforma::create(['nombre' => 'Correo']);
    $tipoCliente = TipoCliente::create(['nombre' => 'Activo']);
    $cliente = Cliente::create([
        'nombre' => 'Cliente Alquiler',
        'ruc' => '12345678901',
        'telefono' => '999999999',
        'correo' => 'cliente@example.com',
        'tipo_cliente_id' => $tipoCliente->id,
    ]);

    $ventas = User::factory()->create();
    $ventas->assignRole('ventas');
    Sanctum::actingAs($ventas);

    $this->postJson('/api/cotizaciones/completa', [
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'plataforma_id' => $plataforma->id,
        'titulo' => 'Alquiler laptops',
        'modo_distribucion' => 'POR_CANTIDAD',
        'moneda_id' => $usd->id,
        'items' => [[
            'descripcion' => 'Laptop rental',
            'cantidad' => 2,
            'costo_base' => 100,
            'margen' => 80,
            'garantia_meses' => 3,
            'tipo' => 'personalizado',
        ]],
        'costos' => [[
            'tipo' => 'Flete',
            'monto' => 40,
        ]],
    ])->assertOk();

    $cotizacion = Cotizacion::with('items')->firstOrFail();
    $item = $cotizacion->items->first();

    expect((int) $cotizacion->moneda_id)->toBe($pen->id);
    expect((float) $item->costo_unitario)->toBe(120.0);
    expect((float) $item->precio_venta)->toBe(600.0);
    expect((float) $item->subtotal)->toBe(3600.0);
    expect((float) $cotizacion->subtotal)->toBe(3600.0);
    expect((float) $cotizacion->igv)->toBe(648.0);
    expect((float) $cotizacion->total)->toBe(4248.0);

    $html = view('pdfs.cotizaciones.alquiler-privado', [
        'cotizacion' => $cotizacion->load([
            'cliente',
            'items.producto',
            'items.productoExterno',
            'items.proveedores',
            'user.profile',
            'plantilla',
            'moneda',
        ]),
    ])->render();

    expect($html)->toContain('Precio Unit Mensual');
    expect($html)->toContain('Precio Cantidad Mensual');
    expect($html)->toContain('Precio Total x Meses');
    expect($html)->toContain('Periodo');

    $htmlEstado = view('pdfs.cotizaciones.alquiler-estado', [
        'cotizacion' => $cotizacion,
    ])->render();

    expect($htmlEstado)->toContain('Precio Unit Mensual');
    expect($htmlEstado)->toContain('Precio Cantidad Mensual');
    expect($htmlEstado)->toContain('Precio Total x Meses');
    expect($htmlEstado)->toContain('Periodo');
});
