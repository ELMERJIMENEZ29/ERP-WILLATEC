<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Services\ProductoSkuService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('detecta sku legacy y conserva sku manual no legacy', function () {
    $service = app(ProductoSkuService::class);

    expect($service->esSkuLegacy(null, '0013'))->toBeTrue()
        ->and($service->esSkuLegacy('', '0013'))->toBeTrue()
        ->and($service->esSkuLegacy('0013', '0013'))->toBeTrue()
        ->and($service->esSkuLegacy('LAP-LEN-T14G5-001', '0013'))->toBeFalse();
});

test('genera sku independiente del codigo interno y evita colisiones', function () {
    $categoria = Categoria::create(['nombre' => 'LAPTOPS']);
    $service = app(ProductoSkuService::class);

    $producto = Producto::create([
        'nombre' => 'Laptop Lenovo ThinkPad T14 Gen 5',
        'sku' => '0013',
        'codigo' => '0013',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad T14 Gen 5',
        'categoria_id' => $categoria->id,
    ]);

    $sku = $service->generarSkuParaProducto($producto->fresh(['categoria']));

    expect($sku)->toStartWith('LAP-LEN-T14G5-')
        ->and($sku)->not->toBe($producto->codigo);

    $producto->forceFill(['sku' => $sku])->save();

    $segundo = Producto::create([
        'nombre' => 'Laptop Lenovo ThinkPad T14 Gen 5',
        'sku' => '0014',
        'codigo' => '0014',
        'marca' => 'Lenovo',
        'modelo' => 'ThinkPad T14 Gen 5',
        'categoria_id' => $categoria->id,
    ]);

    expect($service->generarSkuParaProducto($segundo->fresh(['categoria'])))
        ->toBe('LAP-LEN-T14G5-002');
});
