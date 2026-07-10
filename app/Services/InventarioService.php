<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ProductoSerie;
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
        ?string $userAgent = null,
        ?float $costoUnitario = null,
        ?string $documentoTipo = null,
        ?string $documentoNumero = null,
        ?string $documentoPath = null,
        ?string $fechaDocumento = null,
        ?string $proveedor = null,
        ?int $proveedorId = null,
        ?int $monedaId = null
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
            userAgent: $userAgent,
            costoUnitario: $costoUnitario,
            documentoTipo: $documentoTipo,
            documentoNumero: $documentoNumero,
            documentoPath: $documentoPath,
            fechaDocumento: $fechaDocumento,
            proveedor: $proveedor,
            proveedorId: $proveedorId,
            monedaId: $monedaId
        );
    }

    public function registrarSalidaDesdeReserva(
        int $productoId,
        float $cantidad,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        string $origen = 'erp',
        ?string $idempotencyKey = null,
        ?int $createdBy = null,
        ?string $observacion = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null,
        ?string $documentoTipo = null,
        ?string $documentoNumero = null,
        ?string $documentoPath = null,
        ?string $fechaDocumento = null,
        ?int $monedaId = null,
        bool $liberarReservaAsociada = true,
        ?array $productoSerieIds = null,
        ?int $ocRecibidaId = null,
        ?int $cotizacionItemId = null
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
            userAgent: $userAgent,
            documentoTipo: $documentoTipo,
            documentoNumero: $documentoNumero,
            documentoPath: $documentoPath,
            fechaDocumento: $fechaDocumento,
            monedaId: $monedaId,
            liberarReservaAsociada: $liberarReservaAsociada,
            productoSerieIds: $productoSerieIds,
            salidaSerieEstado: ProductoSerie::ESTADO_VENDIDO,
            ocRecibidaId: $ocRecibidaId,
            cotizacionItemId: $cotizacionItemId
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
        ?string $userAgent = null,
        ?int $monedaId = null
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
            userAgent: $userAgent,
            monedaId: $monedaId
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
        ?string $userAgent = null,
        ?string $documentoTipo = null,
        ?string $documentoNumero = null,
        ?string $documentoPath = null,
        ?string $fechaDocumento = null,
        ?int $monedaId = null,
        ?array $productoSerieIds = null,
        ?string $salidaSerieEstado = null
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
            userAgent: $userAgent,
            documentoTipo: $documentoTipo,
            documentoNumero: $documentoNumero,
            documentoPath: $documentoPath,
            fechaDocumento: $fechaDocumento,
            monedaId: $monedaId,
            productoSerieIds: $productoSerieIds,
            salidaSerieEstado: $salidaSerieEstado
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
        ?string $userAgent = null,
        ?float $costoUnitario = null,
        ?string $documentoTipo = null,
        ?string $documentoNumero = null,
        ?string $documentoPath = null,
        ?string $fechaDocumento = null,
        ?string $proveedor = null,
        ?int $proveedorId = null,
        ?int $monedaId = null,
        ?array $series = null
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
            userAgent: $userAgent,
            costoUnitario: $costoUnitario,
            documentoTipo: $documentoTipo,
            documentoNumero: $documentoNumero,
            documentoPath: $documentoPath,
            fechaDocumento: $fechaDocumento,
            proveedor: $proveedor,
            proveedorId: $proveedorId,
            monedaId: $monedaId,
            series: $series
        );
    }

    public function ajustarStock(
        int $productoId,
        float $nuevoStock,
        ?string $observacion = null,
        ?int $createdBy = null,
        ?string $ipOrigen = null,
        ?string $userAgent = null
    ): Producto {
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
                'moneda_id' => $producto->moneda_id,
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
        ?string $userAgent = null,
        ?float $costoUnitario = null,
        ?int $monedaId = null,
        ?string $documentoTipo = null,
        ?string $documentoNumero = null,
        ?string $documentoPath = null,
        ?string $fechaDocumento = null,
        ?string $proveedor = null,
        ?int $proveedorId = null,
        bool $liberarReservaAsociada = false,
        ?array $series = null,
        ?array $productoSerieIds = null,
        ?string $salidaSerieEstado = null,
        ?int $ocRecibidaId = null,
        ?int $cotizacionItemId = null
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
            $userAgent,
            $costoUnitario,
            $monedaId,
            $documentoTipo,
            $documentoNumero,
            $documentoPath,
            $fechaDocumento,
            $proveedor,
            $proveedorId,
            $liberarReservaAsociada,
            $series,
            $productoSerieIds,
            $salidaSerieEstado,
            $ocRecibidaId,
            $cotizacionItemId
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
            $costoPromedioAntes = (float) ($producto->costo_promedio ?? $producto->costo_unitario ?? 0);
            $valorStockAntes = (float) ($producto->valor_stock ?? ($stockAntes * $costoPromedioAntes));
            $entradaCantidad = 0.0;
            $salidaCantidad = 0.0;
            $costoMovimiento = $costoUnitario ?? $costoPromedioAntes;
            $monedaMovimientoId = $monedaId ?? $producto->moneda_id;

            match ($tipoMovimiento) {
                InventarioMovimiento::TIPO_ENTRADA,
                InventarioMovimiento::TIPO_DEVOLUCION,
                InventarioMovimiento::TIPO_SINCRONIZACION_WOOCOMMERCE => $producto->stock_actual = $stockAntes + ($entradaCantidad = $cantidad),

                InventarioMovimiento::TIPO_SALIDA => $this->aplicarSalida($producto, $cantidad, $liberarReservaAsociada, $salidaCantidad),

                InventarioMovimiento::TIPO_RESERVA => $producto->stock_reservado = $stockReservado + $this->validarStockDisponible($producto, $cantidad),

                InventarioMovimiento::TIPO_LIBERACION_RESERVA => $producto->stock_reservado = $this->restarSinNegativo($stockReservado, $cantidad, 'stock_reservado'),

                default => throw ValidationException::withMessages([
                    'tipo_movimiento' => 'Tipo de movimiento de inventario no soportado.',
                ]),
            };

            if ($entradaCantidad > 0) {
                $costoMovimiento = max(0, (float) $costoMovimiento);
                if ($monedaMovimientoId) {
                    $producto->moneda_id = $monedaMovimientoId;
                }
                $nuevoValorStock = $valorStockAntes + ($entradaCantidad * $costoMovimiento);
                $nuevoStock = (float) $producto->stock_actual;
                $producto->costo_promedio = $nuevoStock > 0 ? round($nuevoValorStock / $nuevoStock, 4) : 0;
                $producto->costo_unitario = $producto->costo_promedio;
                $producto->precio_referencial = $costoMovimiento;
                $producto->valor_stock = round($nuevoValorStock, 2);
            } elseif ($salidaCantidad > 0) {
                $costoMovimiento = $costoPromedioAntes;
                $producto->costo_promedio = $costoPromedioAntes;
                $producto->costo_unitario = $costoPromedioAntes;
                $producto->valor_stock = round(max(0, (float) $producto->stock_actual) * $costoPromedioAntes, 2);
            } else {
                $producto->costo_promedio = $costoPromedioAntes;
                $producto->valor_stock = round(max(0, (float) $producto->stock_actual) * $costoPromedioAntes, 2);
            }

            $this->recalcularProducto($producto);
            $producto->save();

            $productoSerieId = null;
            $seriesRegistradas = $entradaCantidad > 0
                ? $this->registrarSeriesEntrada(
                    producto: $producto,
                    series: $series ?? [],
                    documentoNumero: $documentoNumero,
                    documentoPath: $documentoPath,
                    proveedorId: $proveedorId,
                    costoUnitario: $costoMovimiento,
                    monedaId: $monedaMovimientoId,
                    fechaDocumento: $fechaDocumento,
                    createdBy: $createdBy
                )
                : [];
            $seriesSalida = $salidaCantidad > 0
                ? $this->registrarSeriesSalida(
                    producto: $producto,
                    cantidad: $cantidad,
                    productoSerieIds: $productoSerieIds ?? [],
                    estadoSalida: $salidaSerieEstado ?? $referenciaTipo ?? ProductoSerie::ESTADO_VENDIDO,
                    fechaSalida: $fechaDocumento ?? now()->toDateString(),
                    ocRecibidaId: $ocRecibidaId,
                    cotizacionItemId: $cotizacionItemId
                )
                : [];

            if (count($seriesRegistradas) === 1) {
                $productoSerieId = $seriesRegistradas[0]->id;
            } elseif (count($seriesSalida) === 1) {
                $productoSerieId = $seriesSalida[0]->id;
            }

            $movimiento = InventarioMovimiento::create([
                'producto_id' => $producto->id,
                'producto_serie_id' => $productoSerieId,
                'tipo_movimiento' => $tipoMovimiento,
                'cantidad' => $cantidad,
                'entrada_cantidad' => $entradaCantidad,
                'salida_cantidad' => $salidaCantidad,
                'stock_antes' => $stockAntes,
                'stock_despues' => (float) $producto->stock_actual,
                'saldo_cantidad' => (float) $producto->stock_actual,
                'costo_unitario' => $costoMovimiento,
                'moneda_id' => $monedaMovimientoId,
                'costo_promedio_antes' => $costoPromedioAntes,
                'costo_promedio_despues' => (float) $producto->costo_promedio,
                'valor_movimiento' => round($cantidad * $costoMovimiento, 2),
                'valor_stock_despues' => (float) $producto->valor_stock,
                'referencia_tipo' => $referenciaTipo,
                'referencia_id' => $referenciaId,
                'origen' => $origen,
                'idempotency_key' => $idempotencyKey,
                'observacion' => $observacion,
                'documento_tipo' => $documentoTipo,
                'documento_numero' => $documentoNumero,
                'documento_path' => $documentoPath,
                'fecha_documento' => $fechaDocumento,
                'proveedor' => $proveedor,
                'proveedor_id' => $proveedorId,
                'ip_origen' => $ipOrigen,
                'user_agent' => $userAgent,
                'created_by' => $createdBy,
            ]);

            if (count($seriesRegistradas) > 0) {
                $movimiento->productoSeries()->sync(
                    collect($seriesRegistradas)->pluck('id')->all()
                );
            } elseif (count($seriesSalida) > 0) {
                $movimiento->productoSeries()->sync(
                    collect($seriesSalida)->pluck('id')->all()
                );
            }

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

    private function aplicarSalida(Producto $producto, float $cantidad, bool $liberarReservaAsociada, float &$salidaCantidad): void
    {
        $producto->stock_actual = $this->restarSinNegativo((float) $producto->stock_actual, $cantidad, 'stock_actual');
        $salidaCantidad = $cantidad;

        if ($liberarReservaAsociada) {
            $producto->stock_reservado = $this->restarSinNegativo((float) $producto->stock_reservado, $cantidad, 'stock_reservado');
        }
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
        $producto->valor_stock = round($stockActual * (float) ($producto->costo_promedio ?? 0), 2);
    }

    /**
     * @param  array<int, string|null>  $series
     * @return array<int, ProductoSerie>
     */
    private function registrarSeriesEntrada(
        Producto $producto,
        array $series,
        ?string $documentoNumero,
        ?string $documentoPath,
        ?int $proveedorId,
        ?float $costoUnitario,
        ?int $monedaId,
        ?string $fechaDocumento,
        ?int $createdBy
    ): array {
        $seriesNormalizadas = collect($series)
            ->map(fn ($serie) => trim((string) $serie))
            ->filter()
            ->unique()
            ->values();

        if ($seriesNormalizadas->isEmpty()) {
            return [];
        }

        return $seriesNormalizadas
            ->map(function (string $serie) use (
                $producto,
                $documentoNumero,
                $documentoPath,
                $proveedorId,
                $costoUnitario,
                $monedaId,
                $fechaDocumento,
                $createdBy
            ): ProductoSerie {
                return ProductoSerie::query()->updateOrCreate(
                    [
                        'producto_id' => $producto->id,
                        'serie' => $serie,
                    ],
                    [
                        'factura_numero' => $documentoNumero,
                        'documento_path' => $documentoPath,
                        'proveedor_id' => $proveedorId,
                        'costo_unitario' => $costoUnitario,
                        'moneda_id' => $monedaId,
                        'fecha_ingreso' => $fechaDocumento,
                        'estado' => ProductoSerie::ESTADO_DISPONIBLE,
                        'created_by' => $createdBy,
                    ]
                );
            })
            ->all();
    }

    /**
     * @param  array<int, int|string|null>  $productoSerieIds
     * @return array<int, ProductoSerie>
     */
    private function registrarSeriesSalida(
        Producto $producto,
        float $cantidad,
        array $productoSerieIds,
        string $estadoSalida,
        string $fechaSalida,
        ?int $ocRecibidaId,
        ?int $cotizacionItemId
    ): array {
        $seriesDisponibles = ProductoSerie::query()
            ->where('producto_id', $producto->id)
            ->where('estado', ProductoSerie::ESTADO_DISPONIBLE)
            ->count();

        if ($seriesDisponibles === 0 && empty($productoSerieIds)) {
            return [];
        }

        if (floor($cantidad) !== $cantidad) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Para productos seriados la cantidad de salida debe ser entera.',
            ]);
        }

        $ids = collect($productoSerieIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->count() !== (int) $cantidad) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Selecciona una serie por cada unidad que sale.',
            ]);
        }

        $series = ProductoSerie::query()
            ->where('producto_id', $producto->id)
            ->whereIn('id', $ids->all())
            ->lockForUpdate()
            ->get();

        if ($series->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Una o mas series seleccionadas no pertenecen al producto.',
            ]);
        }

        $seriesNoDisponibles = $series
            ->filter(fn (ProductoSerie $serie): bool => $serie->estado !== ProductoSerie::ESTADO_DISPONIBLE)
            ->pluck('serie')
            ->filter()
            ->values();

        if ($seriesNoDisponibles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Hay series seleccionadas que ya no estan disponibles: '.$seriesNoDisponibles->join(', '),
            ]);
        }

        $series->each(function (ProductoSerie $serie) use ($estadoSalida, $fechaSalida, $ocRecibidaId, $cotizacionItemId): void {
            $serie->forceFill([
                'estado' => $estadoSalida,
                'fecha_salida' => $fechaSalida,
                'oc_recibida_id' => $ocRecibidaId,
                'cotizacion_item_id' => $cotizacionItemId,
            ])->save();
        });

        return $series->all();
    }
}
