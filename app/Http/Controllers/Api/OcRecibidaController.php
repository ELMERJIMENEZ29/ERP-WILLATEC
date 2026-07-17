<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\EstadoCotizacion;
use App\Models\InventarioMovimiento;
use App\Models\OcDocumentoAdicional;
use App\Models\OcRecibida;
use App\Models\OcRecibidaItem;
use App\Models\ProductoSerie;
use App\Models\User;
use App\Notifications\OcRecibidaRegistradaNotification;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OcRecibidaController extends Controller
{
    public function preview(Cotizacion $cotizacion)
    {
        $cotizacion->load(['cliente', 'items.proveedores']);

        return response()->json([
            'cotizacion' => [
                'id' => $cotizacion->id,
                'numero' => $cotizacion->numero,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_ruc' => $cotizacion->cliente_ruc,
                'fecha_recepcion' => now()->toDateString(),
            ],
            'items' => $cotizacion->items->map(fn ($item): array => [
                'cotizacion_item_id' => $item->id,
                'descripcion' => $item->descripcion,
                'codigo' => $item->codigo,
                'marca' => $item->marca,
                'unidad_medida' => $item->unidad_medida,
                'cantidad_cotizada' => $item->cantidad,
                'cantidad_recibida' => $item->cantidad,
                'seleccionado' => true,
                'proveedores' => $item->proveedores,
            ])->values(),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'estado' => 'nullable|in:pendiente,en_proceso,por_entrega,atendido,cancelado',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = OcRecibida::query()
            ->with(['cotizacion:id,numero,titulo', 'cliente:id,nombre,ruc', 'documentosAdicionales'])
            ->withCount([
                'items as items_total' => fn ($query) => $query->where('seleccionado', true),
                'items as items_comprados' => fn ($query) => $query->where('seleccionado', true)->where('comprado', true),
                'items as items_entregados' => fn ($query) => $query->where('seleccionado', true)->where('entregado', true),
            ]);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search): void {
                $query->where('numero', 'like', "%{$search}%")
                    ->orWhere('cliente_nombre', 'like', "%{$search}%")
                    ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery->where('numero', 'like', "%{$search}%"));
            });
        }

        $paginator = $query->latest()->paginate($request->integer('per_page', 10));

        $paginator->getCollection()->each(function (OcRecibida $ocRecibida): void {
            $this->sincronizarCompradoConInventario($ocRecibida->load('items.cotizacionItem'));
            $ocRecibida->unsetRelation('items');
            $ocRecibida->load(['cotizacion:id,numero,titulo', 'cliente:id,nombre,ruc']);
            $ocRecibida->load('documentosAdicionales');
            $ocRecibida->loadCount([
                'items as items_total' => fn ($query) => $query->where('seleccionado', true),
                'items as items_comprados' => fn ($query) => $query->where('seleccionado', true)->where('comprado', true),
                'items as items_entregados' => fn ($query) => $query->where('seleccionado', true)->where('entregado', true),
            ]);
        });

        return response()->json($paginator);
    }

    public function show(OcRecibida $ocRecibida)
    {
        $this->sincronizarCompradoConInventario($ocRecibida->load('items.cotizacionItem'));

        return response()->json(
            $ocRecibida->load([
                'items.cotizacionItem.proveedores',
                'documentosAdicionales',
                'items.cotizacionItem.producto.series' => fn ($query) => $query
                    ->select(['id', 'producto_id', 'serie', 'factura_numero', 'estado', 'fecha_ingreso', 'fecha_salida', 'oc_recibida_id', 'cotizacion_item_id'])
                    ->where(function ($seriesQuery) use ($ocRecibida): void {
                        $seriesQuery->where('estado', ProductoSerie::ESTADO_DISPONIBLE)
                            ->orWhere('oc_recibida_id', $ocRecibida->id);
                    })
                    ->latest(),
                'cotizacion',
                'cliente',
            ])
        );
    }

    public function store(Request $request)
    {
        $this->normalizeBooleanItemFields($request, ['seleccionado']);

        $validated = $request->validate([
            'cotizacion_id' => 'required|exists:cotizaciones,id',
            'fecha_recepcion' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'orden_compra_cliente' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'guia_emision' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'factura_numero' => 'nullable|string|max:100',
            'factura' => 'nullable|file|mimes:pdf,xml,doc,docx,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
            'items.*.cotizacion_item_id' => 'required|integer|exists:cotizacion_items,id',
            'items.*.seleccionado' => 'required|boolean',
            'items.*.cantidad_recibida' => 'required|integer|min:0',
        ]);

        $cotizacion = Cotizacion::with(['items', 'cliente'])->findOrFail($validated['cotizacion_id']);
        $this->ensureCanCreateOcForCotizacion($request, $cotizacion);

        $existingOc = OcRecibida::query()->where('cotizacion_id', $cotizacion->id)->first();
        if ($existingOc) {
            $this->ensureCanEditOc($request, $existingOc);
        }

        $selectedItems = collect($validated['items'])
            ->filter(fn (array $item): bool => (bool) $item['seleccionado'] && (int) $item['cantidad_recibida'] > 0);

        if ($selectedItems->isEmpty()) {
            return response()->json([
                'message' => 'Debe seleccionar al menos un item con cantidad recibida mayor a cero.',
            ], 422);
        }

        $ocRecibida = DB::transaction(function () use ($request, $validated, $cotizacion): OcRecibida {
            $ocRecibida = OcRecibida::firstOrNew(['cotizacion_id' => $cotizacion->id]);

            if (! $ocRecibida->exists) {
                $ocRecibida->numero = $this->generarNumero();
            }

            $ocRecibida->fill([
                'fecha_recepcion' => $validated['fecha_recepcion'] ?? now()->toDateString(),
                'estado' => OcRecibida::ESTADO_PENDIENTE,
                'observaciones' => $validated['observaciones'] ?? null,
                'factura_numero' => $validated['factura_numero'] ?? $ocRecibida->factura_numero,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_ruc' => $cotizacion->cliente_ruc,
                'cliente_contacto' => $cotizacion->cliente_contacto,
                'cliente_correo' => $cotizacion->cliente_correo,
                'cliente_id' => $cotizacion->cliente_id,
                'user_id' => $ocRecibida->user_id ?: $request->user()->id,
            ]);

            if ($request->hasFile('orden_compra_cliente')) {
                $ocRecibida->orden_compra_cliente_path = $this->storeDocumento($request->file('orden_compra_cliente'), 'oc-recibidas');
                $ocRecibida->orden_compra_cliente_uploaded_by = $request->user()?->id;
            }

            if ($request->hasFile('guia_emision')) {
                $ocRecibida->guia_emision_path = $this->storeDocumento($request->file('guia_emision'), 'oc-recibidas');
                $ocRecibida->guia_emision_uploaded_by = $request->user()?->id;
            }

            if ($request->hasFile('factura')) {
                $ocRecibida->factura_path = $this->storeDocumento($request->file('factura'), 'oc-recibidas/facturas');
                $ocRecibida->factura_uploaded_by = $request->user()?->id;
            }

            $ocRecibida->save();
            $ocRecibida->items()->delete();

            $itemsById = $cotizacion->items->keyBy('id');

            foreach ($validated['items'] as $itemData) {
                $cotizacionItem = $itemsById->get((int) $itemData['cotizacion_item_id']);

                if (! $cotizacionItem) {
                    continue;
                }

                OcRecibidaItem::create([
                    'oc_recibida_id' => $ocRecibida->id,
                    'cotizacion_item_id' => $cotizacionItem->id,
                    'descripcion' => $cotizacionItem->descripcion,
                    'codigo' => $cotizacionItem->codigo,
                    'marca' => $cotizacionItem->marca,
                    'unidad_medida' => $cotizacionItem->unidad_medida,
                    'cantidad_cotizada' => $cotizacionItem->cantidad,
                    'cantidad_recibida' => min((int) $itemData['cantidad_recibida'], (int) $cotizacionItem->cantidad),
                    'seleccionado' => (bool) $itemData['seleccionado'] && (int) $itemData['cantidad_recibida'] > 0,
                ]);
            }

            $ocRecibida->refresh()->load('items');
            $this->reservarStockOc($ocRecibida->load('items.cotizacionItem'), $request);
            $this->actualizarEstadoOc($ocRecibida);
            $this->actualizarEstadoCotizacion($cotizacion, $ocRecibida);

            return $ocRecibida->refresh()->load(['items', 'cotizacion.estadoCotizacion']);
        });

        $this->notifyAdministrators(new OcRecibidaRegistradaNotification($ocRecibida, $request->user()));

        return response()->json([
            'message' => 'OC RECIBIDA GUARDADA',
            'oc_recibida' => $ocRecibida,
            'cotizacion' => [
                'id' => $ocRecibida->cotizacion->id,
                'estado' => $ocRecibida->cotizacion->estadoCotizacion?->nombre,
            ],
        ], 201);
    }

    public function updateItems(Request $request, OcRecibida $ocRecibida)
    {
        $this->ensureCanEditOc($request, $ocRecibida);

        if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
            return response()->json([
                'message' => 'No se pueden actualizar items de una OC cancelada.',
            ], 422);
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:oc_recibida_items,id',
            'items.*.comprado' => 'nullable|boolean',
            'items.*.entregado' => 'required|boolean',
            'items.*.producto_serie_ids' => 'nullable|array',
            'items.*.producto_serie_ids.*' => 'integer|exists:producto_series,id',
        ]);

        DB::transaction(function () use ($request, $validated, $ocRecibida): void {
            $this->reservarStockOc($ocRecibida->refresh()->load('items.cotizacionItem'), $request);
            $itemsActuales = $ocRecibida->refresh()->load('items.cotizacionItem')->items->keyBy('id');

            foreach ($validated['items'] as $itemData) {
                $itemActual = $itemsActuales->get((int) $itemData['id']);

                if ((bool) $itemData['entregado'] && ! (bool) $itemActual?->comprado) {
                    throw ValidationException::withMessages([
                        'entregado' => "El item {$itemActual?->descripcion} no puede marcarse como entregado porque aun no esta comprado.",
                    ]);
                }

                if ($itemActual) {
                    $this->sincronizarSeriesItemEntrega($ocRecibida, $itemActual, $itemData);
                }

                $ocRecibida->items()
                    ->whereKey($itemData['id'])
                    ->update([
                        'entregado' => (bool) $itemData['entregado'],
                    ]);
            }

            $this->actualizarEstadoOc($ocRecibida->refresh()->load('items'));
            $ocRecibida->refresh()->load('items.cotizacionItem');

            if ($ocRecibida->estado === OcRecibida::ESTADO_ATENDIDO) {
                $this->registrarSalidaAtendida($ocRecibida, $request, $validated['items']);
            }
        });

        $ocRecibida->refresh()->load('items');

        return response()->json([
            'message' => $ocRecibida->estado === OcRecibida::ESTADO_ATENDIDO
                ? 'Items actualizados. OC atendida.'
                : 'Items actualizados.',
            'estado' => $ocRecibida->estado,
            'documentos_completos' => $ocRecibida->documentos_completos,
            'faltantes' => $ocRecibida->documentos_faltantes,
            'items_pendientes_entrega' => $ocRecibida->items
                ->where('seleccionado', true)
                ->where('entregado', false)
                ->pluck('id')
                ->values(),
        ]);
    }

    public function cancelar(Request $request, OcRecibida $ocRecibida)
    {
        $this->ensureCanEditOc($request, $ocRecibida);

        if ($ocRecibida->estado === OcRecibida::ESTADO_ATENDIDO) {
            return response()->json([
                'message' => 'No se puede cancelar una OC atendida porque ya registro salida de inventario.',
            ], 422);
        }

        if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
            return response()->json([
                'message' => 'La OC ya se encuentra cancelada.',
                'estado' => $ocRecibida->estado,
            ]);
        }

        DB::transaction(function () use ($request, $ocRecibida): void {
            $ocRecibida->loadMissing('items.cotizacionItem', 'cotizacion');

            $inventarioService = app(InventarioService::class);
            $monedaId = $ocRecibida->cotizacion?->moneda_id;

            foreach ($ocRecibida->items->where('seleccionado', true) as $item) {
                $productoId = $item->cotizacionItem?->producto_id;

                if (! $productoId) {
                    $item->forceFill([
                        'comprado' => false,
                        'entregado' => false,
                    ])->save();

                    continue;
                }

                $salidaKey = "oc-recibida:{$ocRecibida->id}:salida:cotizacion-item:{$item->cotizacion_item_id}";
                if (InventarioMovimiento::query()->where('idempotency_key', $salidaKey)->exists()) {
                    throw ValidationException::withMessages([
                        'oc_recibida' => "No se puede cancelar la OC porque el item {$item->descripcion} ya registro salida de inventario.",
                    ]);
                }

                $reservaKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";
                $liberacionKey = "oc-recibida:{$ocRecibida->id}:liberacion-reserva:cotizacion-item:{$item->cotizacion_item_id}";

                if (
                    InventarioMovimiento::query()->where('idempotency_key', $reservaKey)->exists() &&
                    ! InventarioMovimiento::query()->where('idempotency_key', $liberacionKey)->exists()
                ) {
                    $inventarioService->liberarReserva(
                        productoId: (int) $productoId,
                        cantidad: (float) $item->cantidad_recibida,
                        referenciaTipo: 'oc_recibida_cancelada',
                        referenciaId: $ocRecibida->id,
                        origen: 'orden_compra',
                        idempotencyKey: $liberacionKey,
                        createdBy: $request->user()?->id,
                        observacion: "Liberacion de reserva por cancelacion de OC {$ocRecibida->numero}",
                        ipOrigen: $request->ip(),
                        userAgent: $request->userAgent(),
                        monedaId: $monedaId
                    );
                }

                ProductoSerie::query()
                    ->where('producto_id', $productoId)
                    ->where('estado', ProductoSerie::ESTADO_RESERVADO)
                    ->where('oc_recibida_id', $ocRecibida->id)
                    ->where('cotizacion_item_id', $item->cotizacion_item_id)
                    ->update([
                        'estado' => ProductoSerie::ESTADO_DISPONIBLE,
                        'oc_recibida_id' => null,
                        'cotizacion_item_id' => null,
                        'fecha_salida' => null,
                    ]);

                $item->forceFill([
                    'comprado' => false,
                    'entregado' => false,
                ])->save();
            }

            $ocRecibida->forceFill([
                'estado' => OcRecibida::ESTADO_CANCELADO,
            ])->save();
        });

        return response()->json([
            'message' => 'OC cancelada y reservas liberadas.',
            'estado' => $ocRecibida->refresh()->estado,
            'oc_recibida' => $ocRecibida->load(['cotizacion:id,numero,titulo', 'cliente:id,nombre,ruc']),
        ]);
    }

    public function documentos(Request $request, OcRecibida $ocRecibida)
    {
        $this->ensureCanUploadDocuments($request, $ocRecibida);

        $request->validate([
            'orden_compra_cliente' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'guia_emision' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'factura_numero' => 'nullable|string|max:100',
            'factura' => 'nullable|file|mimes:pdf,xml,doc,docx,jpg,jpeg,png|max:10240',
            'documentos_adicionales' => 'nullable|array',
            'documentos_adicionales.*' => 'file|mimes:pdf,xml,doc,docx,jpg,jpeg,png,xls,xlsx|max:10240',
        ]);

        if ($request->hasFile('orden_compra_cliente')) {
            $ocRecibida->orden_compra_cliente_path = $this->storeDocumento($request->file('orden_compra_cliente'), 'oc-recibidas');
            $ocRecibida->orden_compra_cliente_uploaded_by = $request->user()?->id;
        }

        if ($request->hasFile('guia_emision')) {
            $ocRecibida->guia_emision_path = $this->storeDocumento($request->file('guia_emision'), 'oc-recibidas');
            $ocRecibida->guia_emision_uploaded_by = $request->user()?->id;
        }

        if ($request->filled('factura_numero')) {
            $ocRecibida->factura_numero = $request->string('factura_numero')->toString();
        }

        if ($request->hasFile('factura')) {
            $ocRecibida->factura_path = $this->storeDocumento($request->file('factura'), 'oc-recibidas/facturas');
            $ocRecibida->factura_uploaded_by = $request->user()?->id;
        }

        $ocRecibida->save();
        $this->storeDocumentosAdicionales($request, $ocRecibida);
        $this->actualizarEstadoOc($ocRecibida->refresh()->load('items'));

        return response()->json([
            'message' => 'Documentos actualizados',
            'estado' => $ocRecibida->refresh()->estado,
            'documentos_completos' => $ocRecibida->documentos_completos,
            'faltantes' => $ocRecibida->documentos_faltantes,
            'oc_recibida' => $ocRecibida->refresh()->load('documentosAdicionales'),
        ]);
    }

    public function eliminarDocumento(Request $request, OcRecibida $ocRecibida, string $tipo)
    {
        $column = match ($tipo) {
            'orden_compra_cliente' => 'orden_compra_cliente_path',
            'guia_emision' => 'guia_emision_path',
            'factura' => 'factura_path',
            default => null,
        };
        $uploaderColumn = match ($tipo) {
            'orden_compra_cliente' => 'orden_compra_cliente_uploaded_by',
            'guia_emision' => 'guia_emision_uploaded_by',
            'factura' => 'factura_uploaded_by',
            default => null,
        };

        if (! $column || ! $uploaderColumn) {
            return response()->json(['message' => 'Tipo de documento no valido.'], 422);
        }

        $this->ensureCanDeleteDocumento($request, $ocRecibida, $ocRecibida->{$uploaderColumn});

        if ($ocRecibida->{$column}) {
            Storage::disk('public')->delete($ocRecibida->{$column});
        }

        $ocRecibida->forceFill([$column => null, $uploaderColumn => null])->save();
        $this->actualizarEstadoOc($ocRecibida->refresh()->load('items'));

        return response()->json([
            'message' => 'Documento eliminado',
            'oc_recibida' => $ocRecibida->refresh()->load('documentosAdicionales'),
        ]);
    }

    public function eliminarDocumentoAdicional(Request $request, OcRecibida $ocRecibida, OcDocumentoAdicional $documento)
    {
        $this->ensureCanDeleteDocumento($request, $ocRecibida, $documento->created_by);

        if ((int) $documento->oc_recibida_id !== (int) $ocRecibida->id) {
            abort(404);
        }

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return response()->json([
            'message' => 'Documento adicional eliminado',
            'oc_recibida' => $ocRecibida->refresh()->load('documentosAdicionales'),
        ]);
    }

    private function actualizarEstadoOc(OcRecibida $ocRecibida): void
    {
        if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
            return;
        }

        $items = $ocRecibida->items->where('seleccionado', true);

        if ($items->isEmpty() || $items->where('comprado', true)->isEmpty()) {
            $estado = OcRecibida::ESTADO_PENDIENTE;
        } elseif ($items->where('comprado', false)->isNotEmpty()) {
            $estado = OcRecibida::ESTADO_EN_PROCESO;
        } elseif ($items->where('entregado', false)->isNotEmpty() || ! $ocRecibida->documentos_completos) {
            $estado = OcRecibida::ESTADO_POR_ENTREGA;
        } else {
            $estado = OcRecibida::ESTADO_ATENDIDO;
        }

        $ocRecibida->update(['estado' => $estado]);
    }

    private function reservarStockOc(OcRecibida $ocRecibida, Request $request): void
    {
        if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
            return;
        }

        $inventarioService = app(InventarioService::class);
        $ocRecibida->loadMissing('cotizacion');
        $monedaId = $ocRecibida->cotizacion?->moneda_id;

        foreach ($ocRecibida->items->where('seleccionado', true) as $item) {
            $productoId = $item->cotizacionItem?->producto_id;
            $idempotencyKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";

            if (! $productoId || $item->entregado) {
                $item->forceFill([
                    'comprado' => $productoId
                        ? $this->tieneMovimientoInventario($idempotencyKey)
                        : false,
                ])->save();

                continue;
            }

            try {
                $inventarioService->reservarStock(
                    productoId: (int) $productoId,
                    cantidad: (float) $item->cantidad_recibida,
                    referenciaTipo: 'oc_recibida',
                    referenciaId: $ocRecibida->id,
                    origen: 'orden_compra',
                    idempotencyKey: $idempotencyKey,
                    createdBy: $request->user()?->id,
                    observacion: "Reserva por OC recibida {$ocRecibida->numero}",
                    ipOrigen: $request->ip(),
                    userAgent: $request->userAgent(),
                    monedaId: $monedaId
                );

                $item->forceFill(['comprado' => true])->save();
            } catch (ValidationException) {
                $item->forceFill(['comprado' => $this->tieneMovimientoInventario($idempotencyKey)])->save();
            }
        }
    }

    private function tieneMovimientoInventario(string $idempotencyKey): bool
    {
        return InventarioMovimiento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    private function sincronizarCompradoConInventario(OcRecibida $ocRecibida): void
    {
        if ($ocRecibida->estado === OcRecibida::ESTADO_CANCELADO) {
            return;
        }

        $changed = false;

        foreach ($ocRecibida->items->where('seleccionado', true) as $item) {
            $productoId = $item->cotizacionItem?->producto_id;
            $reservaKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";
            $compradoReal = $productoId && $this->tieneMovimientoInventario($reservaKey);

            if ((bool) $item->comprado === (bool) $compradoReal && ($compradoReal || ! $item->entregado)) {
                continue;
            }

            $item->forceFill([
                'comprado' => (bool) $compradoReal,
                'entregado' => $compradoReal ? (bool) $item->entregado : false,
            ])->save();

            $changed = true;
        }

        if ($changed) {
            $this->actualizarEstadoOc($ocRecibida->refresh()->load('items'));
        }
    }

    private function registrarSalidaAtendida(OcRecibida $ocRecibida, Request $request, array $itemsPayload = []): void
    {
        if (! $ocRecibida->factura_numero || ! $ocRecibida->factura_path) {
            throw ValidationException::withMessages([
                'factura' => 'Para atender una OC recibida debe registrar numero de factura y archivo de factura.',
            ]);
        }

        $inventarioService = app(InventarioService::class);
        $ocRecibida->loadMissing('cotizacion');
        $monedaId = $ocRecibida->cotizacion?->moneda_id;
        $itemsPayloadById = collect($itemsPayload)->keyBy(fn (array $item): int => (int) $item['id']);

        foreach ($ocRecibida->items->where('seleccionado', true) as $item) {
            $productoId = $item->cotizacionItem?->producto_id;
            $itemPayload = $itemsPayloadById->get((int) $item->id, []);
            $productoSerieIds = $itemPayload['producto_serie_ids'] ?? [];

            if (! $productoId) {
                throw ValidationException::withMessages([
                    'producto_id' => "El item {$item->descripcion} no esta asociado a un producto interno de inventario.",
                ]);
            }

            if (empty($productoSerieIds)) {
                $productoSerieIds = $this->seriesReservadasParaItem($ocRecibida, $item);
            }

            $reservaKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";
            $liberarReserva = InventarioMovimiento::query()
                ->where('idempotency_key', $reservaKey)
                ->exists();

            $inventarioService->registrarSalidaDesdeReserva(
                productoId: (int) $productoId,
                cantidad: (float) $item->cantidad_recibida,
                referenciaTipo: 'oc_recibida',
                referenciaId: $ocRecibida->id,
                origen: 'orden_compra',
                idempotencyKey: "oc-recibida:{$ocRecibida->id}:salida:cotizacion-item:{$item->cotizacion_item_id}",
                createdBy: $request->user()?->id,
                observacion: "Salida por OC atendida {$ocRecibida->numero}",
                ipOrigen: $request->ip(),
                userAgent: $request->userAgent(),
                documentoTipo: 'factura',
                documentoNumero: $ocRecibida->factura_numero,
                documentoPath: $ocRecibida->factura_path,
                fechaDocumento: now()->toDateString(),
                monedaId: $monedaId,
                liberarReservaAsociada: $liberarReserva,
                productoSerieIds: $productoSerieIds,
                ocRecibidaId: $ocRecibida->id,
                cotizacionItemId: $item->cotizacion_item_id
            );
        }
    }

    private function sincronizarSeriesItemEntrega(OcRecibida $ocRecibida, OcRecibidaItem $item, array $itemData): void
    {
        $productoId = $item->cotizacionItem?->producto_id;

        if (! $productoId) {
            return;
        }

        $seriesProductoCount = ProductoSerie::query()
            ->where('producto_id', $productoId)
            ->count();

        if ($seriesProductoCount === 0) {
            return;
        }

        $entregado = (bool) $itemData['entregado'];
        $cantidad = (float) $item->cantidad_recibida;
        $ids = collect($itemData['producto_serie_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if (! $entregado) {
            $seriesVendidas = ProductoSerie::query()
                ->where('producto_id', $productoId)
                ->where('estado', ProductoSerie::ESTADO_VENDIDO)
                ->where('oc_recibida_id', $ocRecibida->id)
                ->where('cotizacion_item_id', $item->cotizacion_item_id)
                ->exists();

            if ($seriesVendidas) {
                throw ValidationException::withMessages([
                    'entregado' => 'No puedes desmarcar un item que ya registro salida de series vendidas.',
                ]);
            }

            ProductoSerie::query()
                ->where('producto_id', $productoId)
                ->where('estado', ProductoSerie::ESTADO_RESERVADO)
                ->where('oc_recibida_id', $ocRecibida->id)
                ->where('cotizacion_item_id', $item->cotizacion_item_id)
                ->update([
                    'estado' => ProductoSerie::ESTADO_DISPONIBLE,
                    'oc_recibida_id' => null,
                    'cotizacion_item_id' => null,
                    'fecha_salida' => null,
                ]);

            return;
        }

        if (floor($cantidad) !== $cantidad) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Para productos seriados la cantidad entregada debe ser entera.',
            ]);
        }

        if ($ids->count() !== (int) $cantidad) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Selecciona una serie por cada unidad entregada.',
            ]);
        }

        $series = ProductoSerie::query()
            ->where('producto_id', $productoId)
            ->whereIn('id', $ids->all())
            ->lockForUpdate()
            ->get();

        if ($series->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Una o mas series seleccionadas no pertenecen al producto.',
            ]);
        }

        $seriesNoValidas = $series
            ->filter(fn (ProductoSerie $serie): bool => ! (
                $serie->estado === ProductoSerie::ESTADO_DISPONIBLE ||
                (
                    in_array($serie->estado, [ProductoSerie::ESTADO_RESERVADO, ProductoSerie::ESTADO_VENDIDO], true) &&
                    (int) $serie->oc_recibida_id === (int) $ocRecibida->id &&
                    (int) $serie->cotizacion_item_id === (int) $item->cotizacion_item_id
                )
            ))
            ->pluck('serie')
            ->filter()
            ->values();

        if ($seriesNoValidas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'producto_serie_ids' => 'Hay series seleccionadas que ya no estan disponibles: '.$seriesNoValidas->join(', '),
            ]);
        }

        ProductoSerie::query()
            ->where('producto_id', $productoId)
            ->where('estado', ProductoSerie::ESTADO_RESERVADO)
            ->where('oc_recibida_id', $ocRecibida->id)
            ->where('cotizacion_item_id', $item->cotizacion_item_id)
            ->whereNotIn('id', $ids->all())
            ->update([
                'estado' => ProductoSerie::ESTADO_DISPONIBLE,
                'oc_recibida_id' => null,
                'cotizacion_item_id' => null,
                'fecha_salida' => null,
            ]);

        $series
            ->filter(fn (ProductoSerie $serie): bool => $serie->estado !== ProductoSerie::ESTADO_VENDIDO)
            ->each(function (ProductoSerie $serie) use ($ocRecibida, $item): void {
                $serie->forceFill([
                    'estado' => ProductoSerie::ESTADO_RESERVADO,
                    'oc_recibida_id' => $ocRecibida->id,
                    'cotizacion_item_id' => $item->cotizacion_item_id,
                    'fecha_salida' => null,
                ])->save();
            });
    }

    /**
     * @return array<int, int>
     */
    private function seriesReservadasParaItem(OcRecibida $ocRecibida, OcRecibidaItem $item): array
    {
        $productoId = $item->cotizacionItem?->producto_id;

        if (! $productoId) {
            return [];
        }

        return ProductoSerie::query()
            ->where('producto_id', $productoId)
            ->where('oc_recibida_id', $ocRecibida->id)
            ->where('cotizacion_item_id', $item->cotizacion_item_id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function actualizarEstadoCotizacion(Cotizacion $cotizacion, OcRecibida $ocRecibida): void
    {
        $itemsCotizados = $cotizacion->items()->count();
        $itemsSeleccionados = $ocRecibida->items()
            ->where('seleccionado', true)
            ->where('cantidad_recibida', '>', 0)
            ->count();

        $estadoNombre = $itemsCotizados > 0 && $itemsSeleccionados >= $itemsCotizados
            ? 'oc_registrada'
            : 'parcialmente_aprobada';

        $estado = EstadoCotizacion::firstOrCreate(['nombre' => $estadoNombre]);
        $cotizacion->update(['estado_cotizacion_id' => $estado->id]);
    }

    private function generarNumero(): string
    {
        return 'OCR-'.str_pad((string) (OcRecibida::count() + 1), 6, '0', STR_PAD_LEFT);
    }

    private function ensureCanCreateOcForCotizacion(Request $request, Cotizacion $cotizacion): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ((int) $cotizacion->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'Solo el creador de la cotizacion puede asociar la orden de compra.');
    }

    private function ensureCanEditOc(Request $request, OcRecibida $ocRecibida): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ((int) $ocRecibida->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'Solo el usuario que registro esta orden de compra puede editarla.');
    }

    private function ensureCanUploadDocuments(Request $request, OcRecibida $ocRecibida): void
    {
        if ($request->user()->hasAnyRole(['superadmin', 'admin', 'contabilidad'])) {
            return;
        }

        if ((int) $ocRecibida->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'No tienes permisos para subir documentos a esta orden de compra.');
    }

    private function ensureCanDeleteDocumento(Request $request, OcRecibida $ocRecibida, mixed $uploadedBy): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if (
            $request->user()->hasRole('ventas') &&
            (int) $ocRecibida->user_id === (int) $request->user()->id
        ) {
            return;
        }

        if (
            $request->user()->hasAnyRole(['admin', 'contabilidad']) &&
            $uploadedBy &&
            (int) $uploadedBy === (int) $request->user()->id
        ) {
            return;
        }

        abort(403, 'Solo puedes eliminar documentos que hayas subido.');
    }

    private function storeDocumento(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function storeDocumentosAdicionales(Request $request, OcRecibida $ocRecibida): void
    {
        if (! $request->hasFile('documentos_adicionales')) {
            return;
        }

        foreach ($request->file('documentos_adicionales', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ocRecibida->documentosAdicionales()->create([
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $this->storeDocumento($file, 'oc-recibidas/adicionales'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_by' => $request->user()?->id,
            ]);
        }
    }

    private function normalizeBooleanItemFields(Request $request, array $fields): void
    {
        if (! $request->has('items') || ! is_array($request->input('items'))) {
            return;
        }

        $items = collect($request->input('items'))->map(function ($item) use ($fields) {
            if (! is_array($item)) {
                return $item;
            }

            foreach ($fields as $field) {
                if (! array_key_exists($field, $item)) {
                    continue;
                }

                $normalized = filter_var($item[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($normalized !== null) {
                    $item[$field] = $normalized;
                }
            }

            return $item;
        })->all();

        $request->merge(['items' => $items]);
    }

    private function notifyAdministrators(object $notification): void
    {
        User::role(['superadmin', 'admin'])->get()->each->notify($notification);
    }
}
