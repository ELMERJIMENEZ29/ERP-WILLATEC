<?php

use App\Http\Controllers\Api\CotizacionController;
use App\Http\Controllers\Api\OcEmitidaController;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemProveedor;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Models\Moneda;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\TipoCliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crearItemCotizacionParaProveedores(): CotizacionItem
{
    $estadoCotizacion = EstadoCotizacion::create(['nombre' => 'borrador']);
    $estadoItem = EstadoCotizacionItem::create(['nombre' => 'pendiente']);
    $moneda = Moneda::create(['codigo' => 'PEN', 'simbolo' => 'S/']);
    $plantilla = Plantilla::create([
        'nombre' => 'WILLATEC SOLES',
        'incluye_igv' => false,
        'formato_pdf' => 'willatec-soles',
        'activo' => true,
    ]);
    $plataforma = Plataforma::create(['nombre' => 'Correo']);
    $tipoCliente = TipoCliente::create(['nombre' => 'Activo']);
    $cliente = Cliente::create([
        'nombre' => 'Cliente Demo',
        'ruc' => '12345678901',
        'telefono' => '999999999',
        'correo' => 'cliente@example.com',
        'tipo_cliente_id' => $tipoCliente->id,
        'moneda_id' => $moneda->id,
    ]);
    $user = User::factory()->create();

    $cotizacion = Cotizacion::create([
        'numero' => '000001-2026',
        'fecha' => now()->toDateString(),
        'validez_dias' => 10,
        'forma_pago' => 'AL CONTADO',
        'tipo_cambio' => 3.5,
        'titulo' => 'Cotizacion con varios proveedores',
        'modo_distribucion' => 'POR_ITEM',
        'subtotal' => 100,
        'igv' => 18,
        'total' => 118,
        'ganancia' => 10,
        'total_gasto' => 0,
        'cliente_id' => $cliente->id,
        'plantilla_id' => $plantilla->id,
        'estado_cotizacion_id' => $estadoCotizacion->id,
        'user_id' => $user->id,
        'plataforma_id' => $plataforma->id,
        'moneda_id' => $moneda->id,
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'cliente_contacto' => null,
        'cliente_telefono' => $cliente->telefono,
        'cliente_correo' => $cliente->correo,
    ]);

    return CotizacionItem::create([
        'cotizacion_id' => $cotizacion->id,
        'descripcion' => 'Producto externo demo',
        'cantidad' => 1,
        'costo_unitario' => 100,
        'costo_base' => 100,
        'costo_total' => 100,
        'margen' => 10,
        'precio_venta' => 110,
        'subtotal' => 110,
        'ganancia' => 10,
        'orden' => 1,
        'tipo' => 'externo',
        'estado_cotizacion_item_id' => $estadoItem->id,
    ]);
}

function sincronizarProveedoresDeItem(CotizacionItem $item, array $proveedores): void
{
    $method = new ReflectionMethod(CotizacionController::class, 'syncItemProveedores');
    $method->setAccessible(true);
    $method->invoke(app(CotizacionController::class), $item, $proveedores);
}

function proveedoresAgrupadosParaOc(Cotizacion $cotizacion): array
{
    $method = new ReflectionMethod(OcEmitidaController::class, 'proveedoresDeCotizacion');
    $method->setAccessible(true);

    return $method
        ->invoke(app(OcEmitidaController::class), $cotizacion->load('items.proveedores'))
        ->pluck('nombre')
        ->all();
}

test('guarda mas de cinco proveedores distintos en un item de cotizacion', function () {
    $item = crearItemCotizacionParaProveedores();

    $proveedores = collect(range(1, 7))
        ->map(fn (int $index): array => [
            'nombre' => "Proveedor Demo {$index}",
            'link' => "https://proveedor{$index}.test/producto",
            'precio' => 100 + $index,
            'notas' => "Nota {$index}",
        ])
        ->all();

    sincronizarProveedoresDeItem($item, $proveedores);

    expect(CotizacionItemProveedor::where('cotizacion_item_id', $item->id)->count())->toBe(7)
        ->and($item->proveedores()->pluck('nombre')->all())->toBe([
            'Proveedor Demo 1',
            'Proveedor Demo 2',
            'Proveedor Demo 3',
            'Proveedor Demo 4',
            'Proveedor Demo 5',
            'Proveedor Demo 6',
            'Proveedor Demo 7',
        ]);
});

test('conserva proveedores repetidos cuando representan componentes distintos', function () {
    $item = crearItemCotizacionParaProveedores();

    sincronizarProveedoresDeItem($item, [
        ['nombre' => 'Deltron', 'precio' => 100, 'notas' => 'Placa madre'],
        ['nombre' => 'DELTRON ', 'precio' => 101, 'notas' => 'Procesador'],
        ['nombre' => 'Del-tron', 'precio' => 102, 'notas' => 'Memoria RAM'],
        ['nombre' => 'Intcomex', 'precio' => 110],
    ]);

    expect(CotizacionItemProveedor::where('cotizacion_item_id', $item->id)->count())->toBe(4)
        ->and($item->proveedores()->pluck('notas')->all())->toBe([
            'Placa madre',
            'Procesador',
            'Memoria RAM',
            null,
        ])
        ->and(proveedoresAgrupadosParaOc($item->cotizacion))->toBe(['Deltron', 'Intcomex']);
});
