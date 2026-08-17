<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Models\ProductoSerie;
use App\Models\RecepcionCompra;
use App\Models\RecepcionItem;
use App\Models\RequerimientoCompra;
use App\Models\RequerimientoCompraItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecepcionCompraService
{
    public function __construct(private readonly InventarioService $inventarioService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function crear(Compra $compra, array $data, Request $request): RecepcionCompra
    {
        return DB::transaction(function () use ($compra, $data, $request): RecepcionCompra {
            $compra = Compra::query()
                ->lockForUpdate()
                ->with(['items', 'proveedor'])
                ->findOrFail($compra->id);

            if (! in_array($compra->estado, [Compra::ESTADO_CONFIRMADA, Compra::ESTADO_PARCIALMENTE_RECIBIDA], true)) {
                throw ValidationException::withMessages([
                    'compra' => 'Solo se pueden registrar recepciones para compras confirmadas o parcialmente recibidas.',
                ]);
            }

            $recepcion = RecepcionCompra::create([
                'numero' => $this->generarNumero(),
                'compra_id' => $compra->id,
                'proveedor_id' => $compra->proveedor_id,
                'fecha_recepcion' => $data['fecha_recepcion'] ?? now()->toDateString(),
                'estado' => RecepcionCompra::ESTADO_BORRADOR,
                'observacion' => $data['observacion'] ?? null,
                'recibido_por' => $request->user()?->id,
            ]);

            $itemsPorId = $compra->items->keyBy('id');

            foreach ($data['items'] as $itemData) {
                $compraItem = $itemsPorId->get((int) $itemData['compra_item_id']);

                if (! $compraItem) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los items no pertenece a la compra.',
                    ]);
                }

                $cantidad = round((float) $itemData['cantidad'], 2);
                $this->validarCantidadDisponibleRecepcion($compraItem, $cantidad);

                $productoId = (int) ($itemData['producto_id'] ?? $compraItem->producto_id ?? 0);

                if ($productoId <= 0 && $compraItem->producto_externo_id) {
                    $productoId = $this->productoInternoDesdeExterno($compraItem, $itemData, $compra);
                }

                if ($productoId <= 0) {
                    throw ValidationException::withMessages([
                        'producto_id' => "El item {$compraItem->descripcion} requiere un producto interno destino para ingresar stock.",
                    ]);
                }

                RecepcionItem::create([
                    'recepcion_compra_id' => $recepcion->id,
                    'compra_item_id' => $compraItem->id,
                    'producto_id' => $productoId,
                    'descripcion' => $itemData['descripcion'] ?? $compraItem->descripcion,
                    'cantidad' => $cantidad,
                    'costo_unitario_provisional' => $itemData['costo_unitario_provisional'] ?? $compraItem->costo_unitario_estimado,
                    'moneda_id' => $itemData['moneda_id'] ?? $compraItem->moneda_id ?? $compra->moneda_id,
                    'estado' => RecepcionItem::ESTADO_PENDIENTE,
                ]);
            }

            return $recepcion->refresh()->load($this->relations());
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    private function productoInternoDesdeExterno(CompraItem $compraItem, array $itemData, Compra $compra): int
    {
        $externo = ProductoExterno::query()
            ->lockForUpdate()
            ->find($compraItem->producto_externo_id);

        if (! $externo) {
            return 0;
        }

        if ($externo->producto_id) {
            $compraItem->producto_id = $externo->producto_id;
            $compraItem->save();

            return (int) $externo->producto_id;
        }

        $codigo = trim((string) ($externo->codigo ?: ''));
        $productoExistente = null;

        if ($codigo !== '') {
            $productoExistente = Producto::query()
                ->where(function ($query) use ($codigo): void {
                    $query->where('sku', $codigo)
                        ->orWhere('codigo', $codigo);
                })
                ->first();
        }

        $producto = $productoExistente ?: Producto::create([
            'nombre' => $externo->descripcion ?: $compraItem->descripcion,
            'sku' => $this->generarSkuProductoInterno($codigo),
            'codigo' => $codigo !== '' ? $codigo : null,
            'marca' => $externo->marca,
            'descripcion' => $externo->descripcion ?: $compraItem->descripcion,
            'tipo_producto' => 'stock',
            'controla_stock' => true,
            'stock_actual' => 0,
            'stock_reservado' => 0,
            'stock_disponible' => 0,
            'stock_minimo' => 0,
            'stock' => 0,
            'costo_unitario' => $itemData['costo_unitario_provisional'] ?? $compraItem->costo_unitario_estimado ?? 0,
            'costo_promedio' => $itemData['costo_unitario_provisional'] ?? $compraItem->costo_unitario_estimado ?? 0,
            'valor_stock' => 0,
            'precio_venta' => 0,
            'precio_referencial' => $externo->costo_base_referencial,
            'unidad_medida' => $externo->unidad_medida,
            'moneda_id' => $itemData['moneda_id'] ?? $compraItem->moneda_id ?? $compra->moneda_id ?? $externo->moneda_id,
            'imagen' => $externo->imagen,
            'activo' => true,
            'estado' => 'NUEVO',
        ]);

        $externo->producto_id = $producto->id;
        $externo->save();

        $compraItem->producto_id = $producto->id;
        $compraItem->save();

        return (int) $producto->id;
    }

    private function generarSkuProductoInterno(?string $codigo): string
    {
        $base = trim((string) $codigo);

        if ($base !== '' && ! Producto::query()->where('sku', $base)->exists()) {
            return $base;
        }

        $next = (int) (Producto::query()->max('id') ?? 0) + 1;

        do {
            $sku = 'STK-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            $next++;
        } while (Producto::query()->where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmar(RecepcionCompra $recepcion, array $data, Request $request): RecepcionCompra
    {
        return DB::transaction(function () use ($recepcion, $data, $request): RecepcionCompra {
            $recepcion = RecepcionCompra::query()
                ->lockForUpdate()
                ->with(['compra.items.requerimientoCompraItem.requerimiento', 'proveedor', 'items.compraItem'])
                ->findOrFail($recepcion->id);

            if ($recepcion->estado === RecepcionCompra::ESTADO_CONFIRMADA) {
                return $recepcion->load($this->relations());
            }

            if ($recepcion->estado === RecepcionCompra::ESTADO_CANCELADA) {
                throw ValidationException::withMessages([
                    'recepcion' => 'Una recepcion cancelada no puede confirmarse.',
                ]);
            }

            $compra = $recepcion->compra;
            if (! in_array($compra->estado, [Compra::ESTADO_CONFIRMADA, Compra::ESTADO_PARCIALMENTE_RECIBIDA], true)) {
                throw ValidationException::withMessages([
                    'compra' => 'La compra debe estar confirmada para recibir productos.',
                ]);
            }

            $seriesPorItem = collect($data['items'] ?? [])
                ->keyBy(fn (array $item): int => (int) $item['recepcion_item_id']);

            foreach ($recepcion->items as $item) {
                $this->validarCantidadDisponibleRecepcion($item->compraItem, (float) $item->cantidad, $recepcion->id);

                $series = $seriesPorItem->get($item->id)['series'] ?? [];
                $this->validarSeries($item, $series);

                $idempotencyKey = "recepcion-compra:{$recepcion->id}:item:{$item->id}:entrada";

                $this->inventarioService->registrarEntrada(
                    productoId: (int) $item->producto_id,
                    cantidad: (float) $item->cantidad,
                    referenciaTipo: 'recepcion_compra',
                    referenciaId: $recepcion->id,
                    origen: 'compras',
                    idempotencyKey: $idempotencyKey,
                    createdBy: $request->user()?->id,
                    observacion: "Recepcion {$recepcion->numero} de compra {$compra->numero}",
                    ipOrigen: $request->ip(),
                    userAgent: $request->userAgent(),
                    costoUnitario: $item->costo_unitario_provisional === null ? null : (float) $item->costo_unitario_provisional,
                    documentoTipo: 'recepcion_compra',
                    documentoNumero: $recepcion->numero,
                    fechaDocumento: $recepcion->fecha_recepcion?->toDateString() ?? now()->toDateString(),
                    proveedor: $recepcion->proveedor?->nombre,
                    proveedorId: $recepcion->proveedor_id,
                    monedaId: $item->moneda_id,
                    series: $series,
                    recepcionItemId: $item->id,
                    costoTipo: 'provisional'
                );

                $movimiento = InventarioMovimiento::query()->where('idempotency_key', $idempotencyKey)->first();

                $item->forceFill([
                    'estado' => RecepcionItem::ESTADO_CONFIRMADO,
                    'inventario_movimiento_id' => $movimiento?->id,
                ])->save();
            }

            $recepcion->forceFill([
                'estado' => RecepcionCompra::ESTADO_CONFIRMADA,
                'fecha_recepcion' => $data['fecha_recepcion'] ?? $recepcion->fecha_recepcion ?? now()->toDateString(),
                'confirmado_en' => now(),
                'observacion' => $data['observacion'] ?? $recepcion->observacion,
            ])->save();

            $this->recalcularCompra($compra->id);

            return $recepcion->refresh()->load($this->relations());
        });
    }

    public function cancelar(RecepcionCompra $recepcion): RecepcionCompra
    {
        return DB::transaction(function () use ($recepcion): RecepcionCompra {
            $recepcion = RecepcionCompra::query()->lockForUpdate()->with('items')->findOrFail($recepcion->id);

            if ($recepcion->estado === RecepcionCompra::ESTADO_CONFIRMADA) {
                throw ValidationException::withMessages([
                    'recepcion' => 'No se puede cancelar una recepcion confirmada porque ya genero entrada Kardex.',
                ]);
            }

            if ($recepcion->estado === RecepcionCompra::ESTADO_CANCELADA) {
                return $recepcion->load($this->relations());
            }

            $recepcion->items()->update(['estado' => RecepcionItem::ESTADO_CANCELADO]);
            $recepcion->forceFill(['estado' => RecepcionCompra::ESTADO_CANCELADA])->save();

            return $recepcion->refresh()->load($this->relations());
        });
    }

    private function validarCantidadDisponibleRecepcion(CompraItem $compraItem, float $cantidad, ?int $excluirRecepcionId = null): void
    {
        if ($cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad recibida debe ser mayor que cero.']);
        }

        $yaRecibido = RecepcionItem::query()
            ->where('compra_item_id', $compraItem->id)
            ->whereHas('recepcion', function ($query) use ($excluirRecepcionId): void {
                $query->where('estado', RecepcionCompra::ESTADO_CONFIRMADA);

                if ($excluirRecepcionId) {
                    $query->where('id', '!=', $excluirRecepcionId);
                }
            })
            ->sum('cantidad');

        $enBorrador = RecepcionItem::query()
            ->where('compra_item_id', $compraItem->id)
            ->whereHas('recepcion', function ($query) use ($excluirRecepcionId): void {
                $query->where('estado', RecepcionCompra::ESTADO_BORRADOR);

                if ($excluirRecepcionId) {
                    $query->where('id', '!=', $excluirRecepcionId);
                }
            })
            ->sum('cantidad');

        if ((float) $yaRecibido + (float) $enBorrador + $cantidad > (float) $compraItem->cantidad + 0.00001) {
            throw ValidationException::withMessages([
                'cantidad' => "La recepcion supera la cantidad pendiente del item {$compraItem->descripcion}.",
            ]);
        }
    }

    /**
     * @param  array<int, string|null>  $series
     */
    private function validarSeries(RecepcionItem $item, array $series): void
    {
        $seriesNormalizadas = collect($series)
            ->map(fn ($serie) => trim((string) $serie))
            ->filter()
            ->values();

        if ($seriesNormalizadas->isEmpty()) {
            return;
        }

        if (floor((float) $item->cantidad) !== (float) $item->cantidad || $seriesNormalizadas->count() !== (int) $item->cantidad) {
            throw ValidationException::withMessages([
                'series' => 'Para productos seriados la cantidad recibida debe coincidir con la cantidad de series.',
            ]);
        }

        if ($seriesNormalizadas->unique()->count() !== $seriesNormalizadas->count()) {
            throw ValidationException::withMessages([
                'series' => 'Hay series duplicadas en la recepcion.',
            ]);
        }

        $existentes = ProductoSerie::query()
            ->where('producto_id', $item->producto_id)
            ->whereIn('serie', $seriesNormalizadas->all())
            ->pluck('serie')
            ->filter()
            ->values();

        if ($existentes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'series' => 'Hay series que ya existen para este producto: '.$existentes->join(', '),
            ]);
        }
    }

    private function recalcularCompra(int $compraId): void
    {
        $compra = Compra::query()->with('items.requerimientoCompraItem')->lockForUpdate()->findOrFail($compraId);
        $requerimientos = [];

        foreach ($compra->items as $item) {
            $cantidadRecibida = (float) RecepcionItem::query()
                ->where('compra_item_id', $item->id)
                ->whereHas('recepcion', fn ($query) => $query->where('estado', RecepcionCompra::ESTADO_CONFIRMADA))
                ->sum('cantidad');

            $estado = match (true) {
                $cantidadRecibida >= (float) $item->cantidad - 0.00001 => CompraItem::ESTADO_RECIBIDO,
                $cantidadRecibida > 0 => CompraItem::ESTADO_PARCIALMENTE_RECIBIDO,
                default => CompraItem::ESTADO_PENDIENTE,
            };

            $item->forceFill([
                'cantidad_recibida' => $cantidadRecibida,
                'estado' => $estado,
            ])->save();

            if ($item->requerimiento_compra_item_id) {
                $this->recalcularRequerimientoItem((int) $item->requerimiento_compra_item_id);
                $requerimientos[] = $item->requerimientoCompraItem?->requerimiento_compra_id;
            }
        }

        $total = (float) $compra->items->sum(fn (CompraItem $item): float => (float) $item->cantidad);
        $recibido = (float) $compra->items->sum(fn (CompraItem $item): float => (float) $item->fresh()->cantidad_recibida);

        $compra->forceFill([
            'estado' => $recibido >= $total - 0.00001
                ? Compra::ESTADO_RECIBIDA
                : Compra::ESTADO_PARCIALMENTE_RECIBIDA,
        ])->save();

        foreach (array_unique(array_filter($requerimientos)) as $requerimientoId) {
            $this->recalcularEstadoRequerimiento((int) $requerimientoId);
        }
    }

    private function recalcularRequerimientoItem(int $requerimientoItemId): void
    {
        $item = RequerimientoCompraItem::query()->lockForUpdate()->findOrFail($requerimientoItemId);

        $cantidadRecibida = (float) CompraItem::query()
            ->where('requerimiento_compra_item_id', $item->id)
            ->whereHas('compra', fn ($query) => $query->whereIn('estado', [
                Compra::ESTADO_CONFIRMADA,
                Compra::ESTADO_PARCIALMENTE_RECIBIDA,
                Compra::ESTADO_RECIBIDA,
            ]))
            ->sum('cantidad_recibida');

        $item->forceFill(['cantidad_recibida' => $cantidadRecibida])->save();
    }

    private function recalcularEstadoRequerimiento(int $requerimientoId): void
    {
        $requerimiento = RequerimientoCompra::query()->with('items')->lockForUpdate()->findOrFail($requerimientoId);

        if ($requerimiento->estado === RequerimientoCompra::ESTADO_CANCELADO || $requerimiento->items->isEmpty()) {
            return;
        }

        $todosComprados = $requerimiento->items->every(
            fn (RequerimientoCompraItem $item): bool => (float) $item->cantidad_comprada >= (float) $item->cantidad_requerida - 0.00001
        );
        $hayComprado = $requerimiento->items->contains(fn (RequerimientoCompraItem $item): bool => (float) $item->cantidad_comprada > 0);

        $requerimiento->forceFill([
            'estado' => $todosComprados
                ? RequerimientoCompra::ESTADO_COMPRADO
                : ($hayComprado ? RequerimientoCompra::ESTADO_PARCIALMENTE_COMPRADO : RequerimientoCompra::ESTADO_PENDIENTE),
        ])->save();
    }

    private function generarNumero(): string
    {
        $maxId = (int) (RecepcionCompra::query()->max('id') ?? 0);

        do {
            $maxId++;
            $numero = 'REC-'.str_pad((string) $maxId, 6, '0', STR_PAD_LEFT);
        } while (RecepcionCompra::query()->where('numero', $numero)->exists());

        return $numero;
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'compra.proveedor',
            'proveedor',
            'recibidoPor:id,nombres,apellidos,email',
            'items.compraItem.requerimientoCompraItem.requerimiento',
            'items.producto',
            'items.moneda',
            'items.inventarioMovimiento',
            'items.series',
        ];
    }
}
