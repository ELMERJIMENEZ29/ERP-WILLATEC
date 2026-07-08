<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function reservarStock(
        int $productoId,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        string $origen = 'erp',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?string $observacion = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
        return $this->moverStock(
            productoId: $productoId,
            tipoMovimiento: InventarioMovimiento::TIPO_RESERVA,
            cantidad: $cantidad,
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            origen: $origen,
            idempotencyKey: $idempotencyKey,
            createdBy: $createdBy,
            observacion: $observacion,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent
        );
    }

    public function liberarReserva(
        int $productoId,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        string $origen = 'erp',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?string $observacion = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
        return $this->moverStock(
            productoId: $productoId,
            tipoMovimiento: InventarioMovimiento::TIPO_LIBERACION_RESERVA,
            cantidad: $cantidad,
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            origen: $origen,
            idempotencyKey: $idempotencyKey,
            createdBy: $createdBy,
            observacion: $observacion,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent
        );
    }

    public function registrarSalida(
        int $productoId,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        string $origen = 'erp',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?string $observacion = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
        return $this->moverStock(
            productoId: $productoId,
            tipoMovimiento: InventarioMovimiento::TIPO_SALIDA,
            cantidad: $cantidad,
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            origen: $origen,
            idempotencyKey: $idempotencyKey,
            createdBy: $createdBy,
            observacion: $observacion,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent
        );
    }

    public function registrarEntrada(
        int $productoId,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        string $origen = 'erp',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?string $observacion = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
        return $this->moverStock(
            productoId: $productoId,
            tipoMovimiento: InventarioMovimiento::TIPO_ENTRADA,
            cantidad: $cantidad,
            referenciaTipo: $referenciaTipo,
            referenciaId: $referenciaId,
            origen: $origen,
            idempotencyKey: $idempotencyKey,
            createdBy: $createdBy,
            observacion: $observacion,
            ipOrigen: $ipOrigen,
            userAgent: $userAgent
        );
    }

    public function ajustarStock(
        int $productoId,
        float $nuevoStock,
        ?string $observacion = null,
        ?int $createdBy = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto
    {
        return DB::transaction(function () use ($productoId, $nuevoStock, $observacion, $createdBy, $ipOrigen, $userAgent): Producto {
            $producto = Producto::query()->lockForUpdate()->findOrFail($productoId);

            if (! $producto->controla_stock) {
                return $producto;
            }

            if ($nuevoStock < 0) {
                throw ValidationException::withMessages([
                    'nuevo_stock' => 'El stock no puede ser negativo.',
                ]);
            }

            $stockAntes = (float) $producto->stock_actual;
            $producto->stock_actual = $nuevoStock;
            $this->recalcularProducto($producto);
            $producto->save();

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo_movimiento' => InventarioMovimiento::TIPO_AJUSTE_MANUAL,
                'cantidad' => abs($nuevoStock - $stockAntes),
                'stock_antes' => $stockAntes,
                'stock_despues' => (float) $producto->stock_actual,
                'origen' => 'ajuste_manual',
                'observacion' => $observacion,
                'ip_origen' => $ipOrigen,
                'user_agent' => $userAgent,
                'created_by' => $createdBy,
            ]);

            return $producto->refresh();
        });
    }

    public function recalcularStockDisponible(int $productoId): Producto
    {
        return DB::transaction(function () use ($productoId): Producto {
            $producto = Producto::query()->lockForUpdate()->findOrFail($productoId);
            $this->recalcularProducto($producto);
            $producto->save();

            return $producto->refresh();
        });
    }

    private function moverStock(
        int $productoId,
        string $tipoMovimiento,
        float $cantidad,
        ?string $referenciaTipo,
        ?int $referenciaId,
        string $origen,
        ?string $idempotencyKey,
        ?int $createdBy,
        ?string $observacion,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
        return DB::transaction(function () use (
            $productoId,
            $tipoMovimiento,
            $cantidad,
            $referenciaTipo,
            $referenciaId,
            $origen,
            $idempotencyKey,
            $createdBy,
            $observacion,
            $ipOrigen,
            $userAgent
        ): Producto {
            if ($idempotencyKey) {
                $movimientoExistente = InventarioMovimiento::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($movimientoExistente) {
                    return Producto::findOrFail($productoId);
                }
            }

            if ($cantidad <= 0) {
                throw ValidationException::withMessages([
                    'cantidad' => 'La cantidad debe ser mayor a cero.',
                ]);
            }

            $producto = Producto::query()->lockForUpdate()->findOrFail($productoId);

            if (! $producto->controla_stock) {
                return $producto;
            }

            $stockAntes = (float) $producto->stock_actual;
            $stockReservado = (float) $producto->stock_reservado;

            match ($tipoMovimiento) {
                InventarioMovimiento::TIPO_ENTRADA,
                InventarioMovimiento::TIPO_DEVOLUCION,
                InventarioMovimiento::TIPO_SINCRONIZACION_WOOCOMMERCE => $producto->stock_actual = $stockAntes + $cantidad,

                InventarioMovimiento::TIPO_SALIDA => $producto->stock_actual = $this->restarSinNegativo($stockAntes, $cantidad, 'stock_actual'),

                InventarioMovimiento::TIPO_RESERVA => $producto->stock_reservado = $stockReservado + $this->validarStockDisponible($producto, $cantidad),

                InventarioMovimiento::TIPO_LIBERACION_RESERVA => $producto->stock_reservado = $this->restarSinNegativo($stockReservado, $cantidad, 'stock_reservado'),

                default => throw ValidationException::withMessages([
                    'tipo_movimiento' => 'Tipo de movimiento de inventario no soportado.',
                ]),
            };

            $this->recalcularProducto($producto);
            $producto->save();

            InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'tipo_movimiento' => $tipoMovimiento,
                'cantidad' => $cantidad,
                'stock_antes' => $stockAntes,
                'stock_despues' => (float) $producto->stock_actual,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'origen' => $origen,
                'idempotency_key' => $idempotencyKey,
                'observacion' => $observacion,
                'ip_origen' => $ipOrigen,
                'user_agent' => $userAgent,
                'created_by' => $createdBy,
            ]);

            return $producto->refresh();
        });
    }

    private function validarStockDisponible(Producto $producto, float $cantidad): float
    {
        if ((float) $producto->stock_disponible < $cantidad) {
            throw ValidationException::withMessages([
                'stock' => 'No hay stock disponible suficiente para reservar.',
            ]);
        }

        return $cantidad;
    }

    private function restarSinNegativo(float $valorActual, float $cantidad, string $campo): float
    {
        $nuevoValor = $valorActual - $cantidad;

        if ($nuevoValor < 0) {
            throw ValidationException::withMessages([
                $campo => 'La operación dejaría el stock en negativo.',
            ]);
        }

        return $nuevoValor;
    }

    private function recalcularProducto(Producto $producto): void
    {
        $stockActual = max(0, (float) $producto->stock_actual);
        $stockReservado = max(0, (float) $producto->stock_reservado);

        $producto->stock_actual = $stockActual;
        $producto->stock_reservado = $stockReservado;
        $producto->stock_disponible = max(0, $stockActual - $stockReservado);
        $producto->stock = (int) round($stockActual);
    }
}
