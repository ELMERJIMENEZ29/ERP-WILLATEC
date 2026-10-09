<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComprobanteRequest;
use App\Models\Comprobante;
use App\Services\ComprobanteService;
use App\Services\Sunat\SunatXmlService;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComprobanteController extends Controller
{
    public function __construct(
        private readonly ComprobanteService $comprobanteService,
        private readonly SunatXmlService $sunatXmlService,
        private readonly WorkflowNotificationService $notifications
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
}
