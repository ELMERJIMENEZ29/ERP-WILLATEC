<?php

use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\User;
use App\Services\InventarioService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('producto de stock requiere sku unico desde api', function () {
    $this->seed(RoleSeeder::class);

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    Sanctum::actingAs($superadmin);

    $this->postJson('/api/productos', [
        'nombre' => 'Laptop Demo',
        'sku' => 'SKU-001',
        'controla_stock' => true,
        'stock_actual' => 5,
    ])->assertCreated();

    $this->postJson('/api/productos', [
        'nombre' => 'Laptop Duplicada',
        'sku' => 'SKU-001',
        'controla_stock' => true,
        'stock_actual' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sku');

    $this->postJson('/api/productos', [
        'nombre' => 'Producto sin SKU',
        'controla_stock' => true,
        'stock_actual' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sku');
});

test('inventario registra entrada salida reserva liberacion e idempotencia', function () {
    $producto = Producto::create([
        'nombre' => 'Laptop Inventario',
        'sku' => 'INV-001',
        'codigo' => 'INV-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 10,
        'stock_reservado' => 0,
        'stock_disponible' => 10,
        'stock' => 10,
        'activo' => true,
    ]);

    $service = app(InventarioService::class);

    $service->registrarEntrada($producto->id, 5, origen: 'erp', idempotencyKey: 'entrada-1');
    expect((float) $producto->refresh()->stock_actual)->toBe(15.0)
        ->and((float) $producto->stock_disponible)->toBe(15.0);

    $service->registrarEntrada($producto->id, 5, origen: 'erp', idempotencyKey: 'entrada-1');
    expect((float) $producto->refresh()->stock_actual)->toBe(15.0)
        ->and(InventarioMovimiento::where('idempotency_key', 'entrada-1')->count())->toBe(1);

    $service->reservarStock($producto->id, 4, origen: 'erp', idempotencyKey: 'reserva-1');
    expect((float) $producto->refresh()->stock_reservado)->toBe(4.0)
        ->and((float) $producto->stock_disponible)->toBe(11.0);

    $service->liberarReserva($producto->id, 1, origen: 'erp', idempotencyKey: 'libera-1');
    expect((float) $producto->refresh()->stock_reservado)->toBe(3.0)
        ->and((float) $producto->stock_disponible)->toBe(12.0);

    $service->registrarSalida($producto->id, 2, origen: 'erp', idempotencyKey: 'salida-1');
    expect((float) $producto->refresh()->stock_actual)->toBe(13.0)
        ->and((float) $producto->stock_disponible)->toBe(10.0)
        ->and($producto->stock)->toBe(13);
});

test('inventario no descuenta productos que no controlan stock', function () {
    $producto = Producto::create([
        'nombre' => 'Servicio Instalacion',
        'sku' => null,
        'tipo_producto' => 'servicio',
        'controla_stock' => false,
        'stock_actual' => 0,
        'stock_reservado' => 0,
        'stock_disponible' => 0,
        'stock' => 0,
        'activo' => true,
    ]);

    app(InventarioService::class)->registrarSalida($producto->id, 10, origen: 'erp', idempotencyKey: 'servicio-1');

    expect((float) $producto->refresh()->stock_actual)->toBe(0.0)
        ->and(InventarioMovimiento::where('idempotency_key', 'servicio-1')->exists())->toBeFalse();
});

test('ajuste manual registra usuario ip y user agent', function () {
    $this->seed(RoleSeeder::class);

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    Sanctum::actingAs($superadmin);

    $producto = Producto::create([
        'nombre' => 'Mouse Inventario',
        'sku' => 'INV-CTX-001',
        'codigo' => 'INV-CTX-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 5,
        'stock_reservado' => 0,
        'stock_disponible' => 5,
        'stock' => 5,
        'activo' => true,
    ]);

    $this
        ->withServerVariables([
            'REMOTE_ADDR' => '192.168.10.20',
            'HTTP_USER_AGENT' => 'ERP-Test-Agent/1.0',
        ])
        ->postJson("/api/productos/{$producto->id}/ajustar-stock", [
            'nuevo_stock' => 8,
            'observacion' => 'Conteo fisico',
        ])
        ->assertOk();

    $movimiento = InventarioMovimiento::where('producto_id', $producto->id)->latest()->first();

    expect($movimiento)->not->toBeNull()
        ->and($movimiento->created_by)->toBe($superadmin->id)
        ->and($movimiento->ip_origen)->toBe('192.168.10.20')
        ->and($movimiento->user_agent)->toBe('ERP-Test-Agent/1.0');
});

test('superadmin consulta movimientos de inventario con filtros', function () {
    $this->seed(RoleSeeder::class);

    $superadmin = User::factory()->create();
    $superadmin->assignRole('superadmin');

    Sanctum::actingAs($superadmin);

    $producto = Producto::create([
        'nombre' => 'Teclado Auditoria',
        'sku' => 'INV-AUD-001',
        'codigo' => 'INV-AUD-001',
        'tipo_producto' => 'stock',
        'controla_stock' => true,
        'stock_actual' => 10,
        'stock_reservado' => 0,
        'stock_disponible' => 10,
        'stock' => 10,
        'activo' => true,
    ]);

    app(InventarioService::class)->registrarEntrada(
        productoId: $producto->id,
        cantidad: 2,
        origen: 'erp',
        idempotencyKey: 'auditoria-entrada-1',
        createdBy: $superadmin->id,
        observacion: 'Ingreso inicial',
        ipOrigen: '10.0.0.15',
        userAgent: 'Audit-Agent/1.0',
    );

    $this
        ->getJson('/api/inventario/movimientos?search=Teclado&tipo_movimiento=entrada')
        ->assertOk()
        ->assertJsonPath('data.0.producto.sku', 'INV-AUD-001')
        ->assertJsonPath('data.0.created_by.id', $superadmin->id)
        ->assertJsonPath('data.0.ip_origen', '10.0.0.15')
        ->assertJsonPath('data.0.user_agent', 'Audit-Agent/1.0');
});
