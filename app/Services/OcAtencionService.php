<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\OcAtencion;
use App\Models\OcAtencionItem;
use App\Models\OcRecibida;
use App\Models\OcRecibidaItem;
use App\Models\ProductoSerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OcAtencionService
{
    public function __construct(private readonly InventarioService $inventarioService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function crear(OcRecibida $ocRecibida, array $data, Request $request): OcAtencion
    {
        return DB::transaction(function () use ($ocRecibida, $data, $request): OcAtencion {
            $ocRecibida = OcRecibida::query()
                ->lockForUpdate()
                ->with(['items.cotizacionItem.producto', 'cotizacion'])
                ->findOrFail($ocRecibida->id);

            if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'oc_recibida' => 'No se puede preparar una atencion para una OC cancelada.',
                ]);
            }

            $atencion = OcAtencion::create([
                'oc_recibida_id' => $ocRecibida->id,
                'numero' => $this->generarNumero(),
                'fecha_atencion' => $data['fecha_atencion'] ?? now(),
                'estado' => OcAtencion::ESTADO_PREPARANDO,
                'tipo_atencion' => $data['tipo_atencion'] ?? 'entrega_cliente',
                'observacion' => $data['observacion'] ?? null,
                'preparado_por' => $request->user()?->id,
                'created_by' => $request->user()?->id,
            ]);

            $itemsPorId = $ocRecibida->items->keyBy('id');

            foreach ($data['items'] as $itemData) {
                $ocItem = $itemsPorId->get((int) $itemData['oc_recibida_item_id']);

                if (! $ocItem || ! $ocItem->seleccionado) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los items no pertenece a la OC o no fue seleccionado.',
                    ]);
                }

                $productoId = $ocItem->cotizacionItem?->producto_id;

                if (! $productoId || ! $ocItem->comprado) {
                    throw ValidationException::withMessages([
                        'items' => "El item {$ocItem->descripcion} aun no tiene stock cubierto o asegurado.",
                    ]);
                }

                $cantidad = round((float) $itemData['cantidad'], 2);
                $this->validarCantidadPendiente($ocItem, $cantidad);

                $atencionItem = OcAtencionItem::create([
                    'oc_atencion_id' => $atencion->id,
                    'oc_recibida_item_id' => $ocItem->id,
                    'producto_id' => $productoId,
                    'descripcion' => $ocItem->descripcion,
                    'codigo' => $ocItem->codigo,
                    'marca' => $ocItem->marca,
                    'unidad_medida' => $ocItem->unidad_medida,
                    'cantidad' => $cantidad,
                    'cantidad_entregada' => 0,
                    'estado' => OcAtencionItem::ESTADO_PENDIENTE,
                ]);

                $serieIds = collect($itemData['producto_serie_ids'] ?? [])
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if ($serieIds !== []) {
                    $this->validarSeriesParaItem($atencionItem, $serieIds, $cantidad);
                    $atencionItem->series()->sync($serieIds);
                }
            }

            $this->actualizarEstadosOc($ocRecibida->refresh()->load('items'));

            return $atencion->refresh()->load([
                'items.series',
                'items.ocRecibidaItem.cotizacionItem.producto',
                'ocRecibida',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmar(OcAtencion $atencion, array $data, Request $request): OcAtencion
    {
        return DB::transaction(function () use ($atencion, $data, $request): OcAtencion {
            $atencion = OcAtencion::query()
                ->lockForUpdate()
                ->with([
                    'items.series',
                    'items.ocRecibidaItem.cotizacionItem',
                    'ocRecibida.items',
                    'ocRecibida.cotizacion',
                ])
                ->findOrFail($atencion->id);

            if ($atencion->estado === OcAtencion::ESTADO_ENTREGADO) {
                return $atencion;
            }

            if ($atencion->estado === OcAtencion::ESTADO_CANCELADO) {
                throw ValidationException::withMessages([
                    'atencion' => 'No se puede confirmar una atencion cancelada.',
                ]);
            }

            $ocRecibida = $atencion->ocRecibida;
            $monedaId = $ocRecibida?->cotizacion?->moneda_id;

            foreach ($atencion->items as $atencionItem) {
                $ocItem = $atencionItem->ocRecibidaItem;

                if (! $ocItem) {
                    throw ValidationException::withMessages([
                        'items' => 'La atencion contiene un item sin referencia a la OC recibida.',
                    ]);
                }

                $cantidad = (float) $atencionItem->cantidad;
                $this->validarCantidadEntregadaPendiente($atencionItem, $cantidad);
                $productoId = $atencionItem->producto_id ?: $ocItem->cotizacionItem?->producto_id;

                if (! $productoId) {
                    throw ValidationException::withMessages([
                        'producto_id' => "El item {$atencionItem->descripcion} no esta asociado a un producto interno.",
                    ]);
                }

                $serieIds = $atencionItem->series->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
                $this->validarSeriesParaConfirmacion($atencionItem, $serieIds, $cantidad);

                $reservaKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$ocItem->cotizacion_item_id}";
                $liberarReserva = InventarioMovimiento::query()->where('idempotency_key', $reservaKey)->exists();

                if (! $liberarReserva) {
                    throw ValidationException::withMessages([
                        'stock' => "El item {$atencionItem->descripcion} no tiene una reserva de stock vigente.",
                    ]);
                }

                $salidaKey = "oc-atencion:{$atencion->id}:item:{$atencionItem->id}:salida";

                $this->inventarioService->registrarSalidaDesdeReserva(
                    productoId: (int) $productoId,
                    cantidad: $cantidad,
                    referenciaTipo: 'oc_atencion',
                    referenciaId: $atencion->id,
                    origen: 'logistica',
                    idempotencyKey: $salidaKey,
                    createdBy: $request->user()?->id,
                    observacion: "Salida por atencion {$atencion->numero} de OC {$ocRecibida->numero}",
                    ipOrigen: $request->ip(),
                    userAgent: $request->userAgent(),
                    documentoTipo: 'oc_atencion',
                    documentoNumero: $atencion->numero,
                    fechaDocumento: now()->toDateString(),
                    monedaId: $monedaId,
                    liberarReservaAsociada: true,
                    productoSerieIds: $serieIds,
                    ocRecibidaId: $ocRecibida->id,
                    cotizacionItemId: $ocItem->cotizacion_item_id
                );

                $movimiento = InventarioMovimiento::query()->where('idempotency_key', $salidaKey)->first();

                $atencionItem->forceFill([
                    'cantidad_entregada' => $cantidad,
                    'inventario_movimiento_id' => $movimiento?->id,
                    'estado' => OcAtencionItem::ESTADO_ENTREGADO,
                ])->save();

                if ($movimiento && Schema::hasColumn('inventario_movimientos', 'oc_atencion_item_id')) {
                    $movimiento->forceFill(['oc_atencion_item_id' => $atencionItem->id])->save();
                }
            }

            $atencion->forceFill([
                'estado' => OcAtencion::ESTADO_ENTREGADO,
                'fecha_entrega' => $data['fecha_entrega'] ?? now(),
                'entregado_por' => $request->user()?->id,
                'observacion' => $data['observacion'] ?? $atencion->observacion,
            ])->save();

            $this->actualizarLegacyItems($atencion->ocRecibida->refresh()->load('items.atencionItems.atencion'));
            $this->actualizarEstadosOc($atencion->ocRecibida->refresh()->load('items'));

            return $atencion->refresh()->load([
                'items.series',
                'items.inventarioMovimiento',
                'items.ocRecibidaItem.cotizacionItem.producto',
                'ocRecibida.items',
            ]);
        });
    }

    public function cancelar(OcAtencion $atencion): OcAtencion
    {
        return DB::transaction(function () use ($atencion): OcAtencion {
            $atencion = OcAtencion::query()->lockForUpdate()->with('items')->findOrFail($atencion->id);

            if ($atencion->estado === OcAtencion::ESTADO_ENTREGADO) {
                throw ValidationException::withMessages([
                    'atencion' => 'No se puede cancelar una atencion entregada porque ya genero salida Kardex.',
                ]);
            }

            $atencion->items()->update(['estado' => OcAtencionItem::ESTADO_CANCELADO]);
            $atencion->forceFill(['estado' => OcAtencion::ESTADO_CANCELADO])->save();

            $this->actualizarEstadosOc($atencion->ocRecibida->refresh()->load('items'));

            return $atencion->refresh()->load('items.series');
        });
    }

    private function validarCantidadPendiente(OcRecibidaItem $ocItem, float $cantidad): void
    {
        if ($cantidad <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor a cero.']);
        }

        $pendiente = $this->cantidadPendientePreparacion($ocItem);

        if ($cantidad > $pendiente) {
            throw ValidationException::withMessages([
                'cantidad' => "La cantidad de {$ocItem->descripcion} supera el pendiente de atencion ({$pendiente}).",
            ]);
        }
    }

    private function validarCantidadEntregadaPendiente(OcAtencionItem $atencionItem, float $cantidad): void
    {
        $ocItem = $atencionItem->ocRecibidaItem;
        $entregadoOtros = OcAtencionItem::query()
            ->where('oc_recibida_item_id', $ocItem->id)
            ->whereKeyNot($atencionItem->id)
            ->whereHas('atencion', fn ($query) => $query->where('estado', OcAtencion::ESTADO_ENTREGADO))
            ->sum('cantidad_entregada');

        $maximo = max(0, (float) $ocItem->cantidad_recibida - (float) $entregadoOtros);

        if ($cantidad > $maximo) {
            throw ValidationException::withMessages([
                'cantidad' => "La entrega de {$atencionItem->descripcion} supera el pendiente real ({$maximo}).",
            ]);
        }
    }

    private function cantidadPendientePreparacion(OcRecibidaItem $ocItem): float
    {
        $cantidadEnAtencionesActivas = OcAtencionItem::query()
            ->where('oc_recibida_item_id', $ocItem->id)
            ->whereHas('atencion', fn ($query) => $query->where('estado', '!=', OcAtencion::ESTADO_CANCELADO))
            ->sum('cantidad');

        return max(0, (float) $ocItem->cantidad_recibida - (float) $cantidadEnAtencionesActivas);
    }

    /**
     * @param  array<int, int>  $serieIds
     */
    private function validarSeriesParaItem(OcAtencionItem $atencionItem, array $serieIds, float $cantidad): void
    {
        if (floor($cantidad) !== $cantidad || count($serieIds) !== (int) $cantidad) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Para productos seriados la cantidad atendida debe coincidir con la cantidad de series seleccionadas.',
            ]);
        }

        $series = ProductoSerie::query()
            ->whereIn('id', $serieIds)
            ->lockForUpdate()
            ->get();

        if ($series->count() !== count($serieIds)) {
            throw ValidationException::withMessages(['producto_serie_ids' => 'Una o mas series seleccionadas no existen.']);
        }

        $productoId = (int) $atencionItem->producto_id;
        $seriesInvalidas = $series->filter(fn (ProductoSerie $serie): bool => (int) $serie->producto_id !== $productoId);

        if ($seriesInvalidas->isNotEmpty()) {
            throw ValidationException::withMessages(['producto_serie_ids' => 'Una o mas series no pertenecen al producto seleccionado.']);
        }

        $seriesUsadas = ProductoSerie::query()
            ->whereIn('id', $serieIds)
            ->whereHas('ocAtencionItems', fn ($query) => $query
                ->whereKeyNot($atencionItem->id)
                ->whereHas('atencion', fn ($atencionQuery) => $atencionQuery
                    ->where('estado', '!=', OcAtencion::ESTADO_CANCELADO)
                )
            )
            ->pluck('serie')
            ->filter()
            ->values();

        if ($seriesUsadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Hay series seleccionadas en otra atencion: '.$seriesUsadas->join(', '),
            ]);
        }
    }

    /**
     * @param  array<int, int>  $serieIds
     */
    private function validarSeriesParaConfirmacion(OcAtencionItem $atencionItem, array $serieIds, float $cantidad): void
    {
        $productoTieneSeries = ProductoSerie::query()
            ->where('producto_id', $atencionItem->producto_id)
            ->exists();

        if (! $productoTieneSeries) {
            return;
        }

        $this->validarSeriesParaItem($atencionItem, $serieIds, $cantidad);
    }

    private function actualizarLegacyItems(OcRecibida $ocRecibida): void
    {
        foreach ($ocRecibida->items->where('seleccionado', true) as $item) {
            $cantidadEntregada = OcAtencionItem::query()
                ->where('oc_recibida_item_id', $item->id)
                ->whereHas('atencion', fn ($query) => $query->where('estado', OcAtencion::ESTADO_ENTREGADO))
                ->sum('cantidad_entregada');

            $item->forceFill([
                'entregado' => (float) $cantidadEntregada >= (float) $item->cantidad_recibida,
            ])->save();
        }
    }

    private function actualizarEstadosOc(OcRecibida $ocRecibida): void
    {
        $items = $ocRecibida->items->where('seleccionado', true);

        if ($items->isEmpty() || $items->where('comprado', true)->isEmpty()) {
            $estado = OcRecibida::ESTADO_PENDIENTE;
        } elseif ($items->where('comprado', false)->isNotEmpty()) {
            $estado = OcRecibida::ESTADO_EN_PROCESO;
        } elseif ($items->where('entregado', false)->isNotEmpty()) {
            $estado = OcRecibida::ESTADO_POR_ENTREGA;
        } else {
            $estado = OcRecibida::ESTADO_ATENDIDO;
        }

        $payload = ['estado' => $estado];

        if (Schema::hasColumn('oc_recibidas', 'estado_logistico')) {
            $payload['estado_logistico'] = $this->estadoLogistico($ocRecibida);
            $payload['estado_comercial'] = match ($estado) {
                OcRecibida::ESTADO_ATENDIDO => OcRecibida::ESTADO_COMERCIAL_CERRADA,
                OcRecibida::ESTADO_EN_PROCESO, OcRecibida::ESTADO_POR_ENTREGA => OcRecibida::ESTADO_COMERCIAL_EN_ATENCION,
                default => $ocRecibida->estado_comercial ?: OcRecibida::ESTADO_COMERCIAL_REGISTRADA,
            };
            $payload['estado_documental'] = $ocRecibida->documentosFaltantes() === []
                ? OcRecibida::ESTADO_DOCUMENTAL_COMPLETO
                : OcRecibida::ESTADO_DOCUMENTAL_INCOMPLETO;
            $payload['estado_financiero'] = $ocRecibida->estado_financiero ?: OcRecibida::ESTADO_FINANCIERO_PENDIENTE;
        }

        $ocRecibida->forceFill($payload)->save();
    }

    private function estadoLogistico(OcRecibida $ocRecibida): string
    {
        $total = (float) $ocRecibida->items->where('seleccionado', true)->sum('cantidad_recibida');

        if ($total <= 0) {
            return OcRecibida::ESTADO_LOGISTICO_PENDIENTE;
        }

        $entregado = OcAtencionItem::query()
            ->whereHas('atencion', fn ($query) => $query
                ->where('oc_recibida_id', $ocRecibida->id)
                ->where('estado', OcAtencion::ESTADO_ENTREGADO)
            )
            ->sum('cantidad_entregada');

        if ((float) $entregado >= $total) {
            return OcRecibida::ESTADO_LOGISTICO_ENTREGADO;
        }

        if ((float) $entregado > 0) {
            return OcRecibida::ESTADO_LOGISTICO_PARCIAL;
        }

        $preparando = OcAtencion::query()
            ->where('oc_recibida_id', $ocRecibida->id)
            ->whereNotIn('estado', [OcAtencion::ESTADO_CANCELADO, OcAtencion::ESTADO_ENTREGADO])
            ->exists();

        return $preparando ? OcRecibida::ESTADO_LOGISTICO_PREPARANDO : OcRecibida::ESTADO_LOGISTICO_PENDIENTE;
    }

    private function generarNumero(): string
    {
        $maxId = (int) (OcAtencion::query()->max('id') ?? 0);

        do {
            $maxId++;
            $numero = 'AT-'.str_pad((string) $maxId, 6, '0', STR_PAD_LEFT);
        } while (OcAtencion::query()->where('numero', $numero)->exists());

        return $numero;
    }
}
