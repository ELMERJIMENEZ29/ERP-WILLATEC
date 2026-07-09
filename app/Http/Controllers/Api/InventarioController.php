<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AjustarStockRequest;
use App\Models\CotizacionItem;
use App\Models\InventarioMovimiento;
use App\Models\OcRecibida;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

class InventarioController extends Controller
{
    public function indexMovimientos(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'producto_id' => 'nullable|integer|exists:productos,id',
            'tipo_movimiento' => 'nullable|string|max:40',
            'origen' => 'nullable|string|max:40',
            'created_by' => 'nullable|integer|exists:users,id',
            'ip_origen' => 'nullable|string|max:45',
            'serie' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:150',
            'modelo' => 'nullable|string|max:150',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre,sku,codigo,serie,marca,modelo',
                'productoSerie:id,producto_id,serie,factura_numero,estado',
                'productoSeries:id,producto_id,serie,factura_numero,estado',
                'moneda:id,codigo,simbolo',
                'proveedorCatalogo:id,nombre,ruc',
                'createdBy:id,nombres,apellidos,email',
            ])
            ->latest();

        if (! empty($validated['producto_id'])) {
            $query->where('producto_id', $validated['producto_id']);
        }

        if (! empty($validated['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $validated['tipo_movimiento']);
        }

        if (! empty($validated['origen'])) {
            $query->where('origen', $validated['origen']);
        }

        if (! empty($validated['created_by'])) {
            $query->where('created_by', $validated['created_by']);
        }

        if (! empty($validated['ip_origen'])) {
            $query->where('ip_origen', 'like', '%'.$validated['ip_origen'].'%');
        }

        if (! empty($validated['serie'])) {
            $serie = $validated['serie'];

            $query->where(function ($query) use ($serie): void {
                $query->whereHas('producto', function ($productoQuery) use ($serie): void {
                    $productoQuery->where('serie', 'like', "%{$serie}%");
                })->orWhereHas('productoSerie', function ($serieQuery) use ($serie): void {
                    $serieQuery->where('serie', 'like', "%{$serie}%");
                })->orWhereHas('productoSeries', function ($seriesQuery) use ($serie): void {
                    $seriesQuery->where('serie', 'like', "%{$serie}%");
                });
            });
        }

        foreach (['marca', 'modelo'] as $field) {
            if (! empty($validated[$field])) {
                $value = $validated[$field];

                $query->whereHas('producto', function ($productoQuery) use ($field, $value): void {
                    $productoQuery->where($field, 'like', "%{$value}%");
                });
            }
        }

        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('observacion', 'like', "%{$search}%")
                    ->orWhere('referencia_tipo', 'like', "%{$search}%")
                    ->orWhere('ip_origen', 'like', "%{$search}%")
                    ->orWhere('documento_numero', 'like', "%{$search}%")
                    ->orWhere('proveedor', 'like', "%{$search}%")
                    ->orWhereHas('productoSerie', function ($query) use ($search): void {
                        $query->where('serie', 'like', "%{$search}%")
                            ->orWhere('factura_numero', 'like', "%{$search}%");
                    })
                    ->orWhereHas('productoSeries', function ($query) use ($search): void {
                        $query->where('serie', 'like', "%{$search}%")
                            ->orWhere('factura_numero', 'like', "%{$search}%");
                    })
                    ->orWhereHas('producto', function ($query) use ($search): void {
                        $query->where('nombre', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%")
                            ->orWhere('marca', 'like', "%{$search}%")
                            ->orWhere('modelo', 'like', "%{$search}%")
                            ->orWhere('serie', 'like', "%{$search}%");
                    });
            });
        }

        $movimientos = $query->paginate($request->integer('per_page', 15));

        $movimientos->getCollection()->transform(function (InventarioMovimiento $movimiento): InventarioMovimiento {
            $movimiento->setAttribute('garantia_info', $this->buildGarantiaInfo($movimiento));

            return $movimiento;
        });

        return response()->json($movimientos);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildGarantiaInfo(InventarioMovimiento $movimiento): ?array
    {
        if (
            $movimiento->tipo_movimiento !== InventarioMovimiento::TIPO_SALIDA ||
            $movimiento->referencia_tipo !== 'oc_recibida' ||
            ! $movimiento->referencia_id
        ) {
            return null;
        }

        $cotizacionItemId = $this->cotizacionItemIdFromMovimiento($movimiento);

        if (! $cotizacionItemId) {
            return null;
        }

        $cotizacionItem = CotizacionItem::query()
            ->select(['id', 'cotizacion_id', 'garantia_meses'])
            ->with('cotizacion:id,numero,titulo')
            ->find($cotizacionItemId);

        if (! $cotizacionItem || ! $cotizacionItem->garantia_meses) {
            return null;
        }

        $ocRecibida = OcRecibida::query()
            ->select(['id', 'numero', 'cliente_nombre', 'cliente_ruc'])
            ->find($movimiento->referencia_id);
        $fechaInicio = Carbon::parse($movimiento->fecha_documento ?: $movimiento->created_at)->startOfDay();
        $fechaFin = $fechaInicio->copy()->addMonths((int) $cotizacionItem->garantia_meses);
        $hoy = now()->startOfDay();

        return [
            'garantia_meses' => (int) $cotizacionItem->garantia_meses,
            'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_vencimiento' => $fechaFin->toDateString(),
            'vigente' => $hoy->lessThanOrEqualTo($fechaFin),
            'dias_restantes' => $hoy->lessThanOrEqualTo($fechaFin) ? $hoy->diffInDays($fechaFin) : 0,
            'oc_numero' => $ocRecibida?->numero,
            'cliente_nombre' => $ocRecibida?->cliente_nombre,
            'cliente_ruc' => $ocRecibida?->cliente_ruc,
            'cotizacion_numero' => $cotizacionItem->cotizacion?->numero,
        ];
    }

    private function cotizacionItemIdFromMovimiento(InventarioMovimiento $movimiento): ?int
    {
        if (! $movimiento->idempotency_key) {
            return null;
        }

        preg_match('/cotizacion-item:(\d+)/', $movimiento->idempotency_key, $matches);

        return isset($matches[1]) ? (int) $matches[1] : null;
    }

    public function show(Producto $producto)
    {
        return response()->json([
            'producto_id' => $producto->id,
            'sku' => $producto->sku,
            'controla_stock' => $producto->controla_stock,
            'stock_actual' => $producto->stock_actual,
            'stock_reservado' => $producto->stock_reservado,
            'stock_disponible' => $producto->stock_disponible,
            'stock_minimo' => $producto->stock_minimo,
            'costo_promedio' => $producto->costo_promedio,
            'valor_stock' => $producto->valor_stock,
            'ultima_sincronizacion' => $producto->ultima_sincronizacion,
            'woocommerce' => $producto->loadMissing('woocommerceProducto')->woocommerceProducto,
        ]);
    }

    public function movimientos(Producto $producto)
    {
        return response()->json(
            $producto->inventarioMovimientos()
                ->with('createdBy:id,nombres,apellidos,email')
                ->latest()
                ->paginate(request()->integer('per_page', 15))
        );
    }

    public function ajustarStock(AjustarStockRequest $request, Producto $producto, InventarioService $inventarioService)
    {
        $productoActualizado = $inventarioService->ajustarStock(
            productoId: $producto->id,
            nuevoStock: (float) $request->input('nuevo_stock'),
            observacion: $request->input('observacion'),
            createdBy: $request->user()?->id,
            ipOrigen: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->json([
            'message' => 'Stock ajustado correctamente',
            'producto' => $productoActualizado,
        ]);
    }

    public function registrarEntrada(Request $request, Producto $producto, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
            'costo_unitario' => 'required|numeric|min:0',
            'moneda_id' => 'required|exists:monedas,id',
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'proveedor' => 'nullable|string|max:255',
            'documento_tipo' => 'nullable|string|max:40',
            'documento_numero' => 'nullable|string|max:100',
            'fecha_documento' => 'nullable|date',
            'observacion' => 'nullable|string',
            'series' => 'nullable|array',
            'series.*' => 'nullable|string|max:150|distinct',
            'factura' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xml,doc,docx|max:10240',
        ]);

        $series = collect($validated['series'] ?? [])
            ->map(fn ($serie) => trim((string) $serie))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($series) > (float) $validated['cantidad']) {
            return response()->json([
                'message' => 'No puedes registrar mas series que la cantidad ingresada.',
                'errors' => [
                    'series' => ['No puedes registrar mas series que la cantidad ingresada.'],
                ],
            ], 422);
        }

        $documentoPath = $request->hasFile('factura')
            ? $this->storeDocumento($request->file('factura'), 'inventario/facturas')
            : null;
        $proveedorCatalogo = ! empty($validated['proveedor_id'])
            ? Proveedor::find($validated['proveedor_id'])
            : null;
        $proveedorNombre = $proveedorCatalogo?->nombre ?? ($validated['proveedor'] ?? null);

        $productoActualizado = $inventarioService->registrarEntrada(
            productoId: $producto->id,
            cantidad: (float) $validated['cantidad'],
            referenciaTipo: 'entrada_manual',
            referenciaId: null,
            origen: 'kardex',
            idempotencyKey: null,
            createdBy: $request->user()?->id,
            observacion: $validated['observacion'] ?? null,
            ipOrigen: $request->ip(),
            userAgent: $request->userAgent(),
            costoUnitario: (float) $validated['costo_unitario'],
            monedaId: (int) $validated['moneda_id'],
            documentoTipo: $validated['documento_tipo'] ?? 'factura',
            documentoNumero: $validated['documento_numero'] ?? null,
            documentoPath: $documentoPath,
            fechaDocumento: $validated['fecha_documento'] ?? null,
            proveedor: $proveedorNombre,
            proveedorId: $proveedorCatalogo?->id,
            series: $series
        );

        return response()->json([
            'message' => 'Entrada registrada en Kardex',
            'producto' => $productoActualizado,
        ]);
    }

    public function registrarSalida(Request $request, Producto $producto, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'cantidad' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:60',
            'moneda_id' => 'nullable|exists:monedas,id',
            'documento_tipo' => 'nullable|string|max:40',
            'documento_numero' => 'nullable|string|max:100',
            'fecha_documento' => 'nullable|date',
            'observacion' => 'nullable|string',
            'documento' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xml,doc,docx|max:10240',
        ]);

        $documentoPath = $request->hasFile('documento')
            ? $this->storeDocumento($request->file('documento'), 'inventario/salidas')
            : null;

        $productoActualizado = $inventarioService->registrarSalida(
            productoId: $producto->id,
            cantidad: (float) $validated['cantidad'],
            referenciaTipo: $validated['motivo'],
            referenciaId: null,
            origen: 'salida_manual',
            idempotencyKey: null,
            createdBy: $request->user()?->id,
            observacion: $validated['observacion'] ?? null,
            ipOrigen: $request->ip(),
            userAgent: $request->userAgent(),
            documentoTipo: $validated['documento_tipo'] ?? null,
            documentoNumero: $validated['documento_numero'] ?? null,
            documentoPath: $documentoPath,
            fechaDocumento: $validated['fecha_documento'] ?? null,
            monedaId: isset($validated['moneda_id']) ? (int) $validated['moneda_id'] : null
        );

        return response()->json([
            'message' => 'Salida registrada en Kardex',
            'producto' => $productoActualizado,
        ]);
    }

    private function storeDocumento(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }
}
