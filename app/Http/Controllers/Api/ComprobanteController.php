<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComprobanteRequest;
use App\Models\Compra;
use App\Models\Comprobante;
use App\Services\ComprobanteService;
use App\Services\NotaCreditoService;
use App\Services\Sunat\SunatXmlService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ComprobanteController extends Controller
{
    public function __construct(
        private readonly ComprobanteService $comprobanteService,
        private readonly SunatXmlService $sunatXmlService,
        private readonly WorkflowNotificationService $notifications,
        private readonly NotaCreditoService $notaCreditoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Comprobante::query()
            ->with([
                'compra',
                'ocRecibida',
                'cotizacion',
                'cliente',
                'proveedor',
                'moneda',
                'creadoPor',
                'cuentaPorPagar:id,comprobante_id,estado',
                'cuentaPorCobrar:id,comprobante_id,estado',
            ])
            ->withCount('items')
            ->latest('id');

        if ($request->filled('tipo_operacion')) {
            $query->where('tipo_operacion', $request->string('tipo_operacion'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('compra_id')) {
            $query->where('compra_id', $request->integer('compra_id'));
        }

        if ($request->filled('oc_recibida_id')) {
            $query->where('oc_recibida_id', $request->integer('oc_recibida_id'));
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->integer('cliente_id'));
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->integer('proveedor_id'));
        }

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->input('buscar'));

            $query->where(function ($query) use ($buscar): void {
                $query
                    ->where('serie', 'like', "%{$buscar}%")
                    ->orWhere('numero', 'like', "%{$buscar}%")
                    ->orWhere('emisor_ruc', 'like', "%{$buscar}%")
                    ->orWhere('emisor_nombre', 'like', "%{$buscar}%")
                    ->orWhere('receptor_ruc', 'like', "%{$buscar}%")
                    ->orWhere('receptor_nombre', 'like', "%{$buscar}%");
            });
        }

        return response()->json(
            $query->paginate(
                min(max((int) $request->input('per_page', 20), 1), 100)
            )
        );
    }

    public function store(StoreComprobanteRequest $request): JsonResponse
    {
        $comprobante = $this->comprobanteService->crear(
            $request->validated(),
            $request->user()?->id
        );
        $this->notifications->contabilidad('Comprobante registrado', "Se registró el comprobante {$comprobante->serie}-{$comprobante->numero}.", '/contabilidad/comprobantes', 'comprobante_creado', ['comprobante_id' => $comprobante->id]);

        return response()->json($comprobante, 201);
    }

    public function storeManual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_operacion' => ['required', 'in:compra,venta,gasto'],
            'compra_id' => ['nullable', 'required_if:tipo_operacion,compra', 'integer', 'exists:compras,id'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'categoria_gasto' => ['nullable', 'required_if:tipo_operacion,gasto', 'in:combustible,servicios,viaticos,limpieza,oficina,otros'],
            'centro_costo' => ['nullable', 'string', 'max:120'],
            'emisor_ruc' => ['nullable', 'string', 'max:20'],
            'emisor_nombre' => ['nullable', 'string', 'max:255'],
            'receptor_ruc' => ['nullable', 'string', 'max:20'],
            'receptor_nombre' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'tipo_comprobante' => ['required', 'string', 'max:10'],
            'serie' => ['required', 'string', 'max:20'],
            'numero' => ['required', 'string', 'max:40'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'condicion_pago' => ['nullable', 'in:contado,cheque_30_dias,credito_15_dias,credito_30_dias,credito_45_dias,credito_60_dias,efectivo_dev,tarjeta_dev'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'igv' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', 'integer', 'exists:monedas,id'],
            'observacion' => ['nullable', 'string', 'max:5000'],
            'detraccion_porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'detraccion_monto_original' => ['nullable', 'numeric', 'min:0'],
            'archivo' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $compra = ! empty($validated['compra_id']) ? Compra::query()->with(['items', 'proveedor'])->findOrFail($validated['compra_id']) : null;
        if ($compra) {
            $validated['proveedor_id'] = $compra->proveedor_id;
            $validated['moneda_id'] ??= $compra->moneda_id;
        }
        $validated['fecha_vencimiento'] ??= $validated['fecha_emision'];
        $validated['fecha_vencimiento_origen'] = $request->filled('fecha_vencimiento') ? 'manual' : 'contado';
        $validated['archivo_path'] = $request->file('archivo')?->store('contabilidad/comprobantes', 'public');
        unset($validated['archivo']);
        $validated['items'] = $compra ? $compra->items->map(fn ($item) => [
            'compra_item_id' => $item->id,
            'descripcion' => $item->descripcion,
            'cantidad' => $item->cantidad,
            'valor_unitario' => $item->costo_unitario_estimado ?? 0,
            'subtotal' => round((float) $item->cantidad * (float) ($item->costo_unitario_estimado ?? 0), 2),
            'igv' => 0,
            'total' => round((float) $item->cantidad * (float) ($item->costo_unitario_estimado ?? 0), 2),
        ])->values()->all() : [[
            'descripcion' => $validated['descripcion'] ?? $validated['observacion'] ?? 'Comprobante manual',
            'cantidad' => 1,
            'valor_unitario' => $validated['subtotal'],
            'subtotal' => $validated['subtotal'],
            'igv' => $validated['igv'],
            'total' => $validated['total'],
        ]];

        try {
            $comprobante = $this->comprobanteService->crear($validated, $request->user()?->id);
        } catch (\Throwable $exception) {
            if ($validated['archivo_path']) {
                Storage::disk('public')->delete($validated['archivo_path']);
            }
            throw $exception;
        }

        $this->notifications->contabilidad('Comprobante PDF registrado', "Se registró manualmente el comprobante {$comprobante->serie}-{$comprobante->numero}.", '/contabilidad/comprobantes', 'comprobante_creado', ['comprobante_id' => $comprobante->id]);

        return response()->json($comprobante, 201);
    }

    public function previewXml(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'xml' => ['bail', 'required', 'file', 'min:1', 'mimes:xml,txt', 'max:5120'],
        ], [
            'xml.min' => 'El archivo XML está vacío. Vuelve a descargarlo o expórtalo nuevamente.',
            'xml.mimes' => 'El archivo seleccionado no es un XML válido.',
        ]);

        return response()->json(
            $this->sunatXmlService->preview($validated['xml'])
        );
    }

    public function show(Comprobante $comprobante): JsonResponse
    {
        return response()->json(
            $comprobante->load($this->comprobanteService->relaciones())
        );
    }

    public function anular(Comprobante $comprobante): JsonResponse
    {
        return response()->json(
            $this->comprobanteService->anular($comprobante)
        );
    }

    public function registrarNotaCredito(Request $request, Comprobante $comprobante): JsonResponse
    {
        $validated = $request->validate([
            'tipo_anulacion' => ['required', 'in:total,parcial'],
            'monto' => ['nullable', 'numeric', 'gt:0'],
            'motivo' => ['required', 'string', 'min:5', 'max:3000'],
            'devolucion_fisica_confirmada' => ['nullable', 'boolean'],
            'xml' => ['required', 'file', 'mimes:xml,txt', 'max:5120'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'items' => ['nullable', 'array'],
            'items.*.comprobante_item_id' => ['nullable', 'integer', 'exists:comprobante_items,id'],
            'items.*.cantidad' => ['nullable', 'numeric', 'gt:0'],
            'items.*.monto' => ['required_with:items', 'numeric', 'gt:0'],
        ]);
        $preview = $this->sunatXmlService->preview($validated['xml']);
        if (! in_array(strtolower((string) $preview['tipo_comprobante']), ['creditnote', '07'], true)) {
            return response()->json(['message' => 'El XML debe corresponder a una nota de crédito.'], 422);
        }
        $referenciaEsperada = strtoupper("{$comprobante->serie}-{$comprobante->numero}");
        if (strtoupper((string) ($preview['documento_referencia'] ?? '')) !== $referenciaEsperada) {
            return response()->json(['message' => "La nota de crédito no referencia al comprobante {$referenciaEsperada}."], 422);
        }
        $validated['serie'] = $preview['serie'];
        $validated['numero'] = $preview['numero'];
        $validated['fecha_emision'] = $preview['fecha_emision'];
        $validated['monto'] = $validated['tipo_anulacion'] === 'total' ? (float) $preview['total'] : ($validated['monto'] ?? (float) $preview['total']);
        $validated['xml_hash'] = $preview['xml_hash'];
        $validated['xml_path'] = $request->file('xml')->store('contabilidad/notas-credito/xml', 'public');
        $validated['pdf_path'] = $request->file('pdf')?->store('contabilidad/notas-credito/pdf', 'public');
        $nota = $this->notaCreditoService->registrar($comprobante, $validated, $request->user()?->id);
        $this->notifications->contabilidad('Nota de crédito registrada', "La nota {$nota->serie}-{$nota->numero} ajustó el comprobante {$comprobante->serie}-{$comprobante->numero}.", '/contabilidad/comprobantes', 'nota_credito_creada', ['comprobante_id' => $comprobante->id, 'nota_credito_id' => $nota->id]);

        return response()->json($nota, 201);
    }
}
