<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerarRequerimientoDesdeOcRequest;
use App\Http\Requests\StoreRequerimientoCompraRequest;
use App\Models\OcRecibida;
use App\Models\RequerimientoCompra;
use App\Services\RequerimientoCompraService;
use Illuminate\Http\Request;

class RequerimientoCompraController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:40'],
            'origen_tipo' => ['nullable', 'string', 'max:40'],
            'oc_recibida_id' => ['nullable', 'integer', 'exists:oc_recibidas,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = RequerimientoCompra::query()
            ->with([
                'ocRecibida:id,numero,cliente_nombre,cotizacion_id',
                'items.producto:id,nombre,sku,codigo',
                'items.productoExterno:id,descripcion,codigo,marca',
                'solicitadoPor:id,nombres,apellidos,email',
                'asignadoA:id,nombres,apellidos,email',
            ])
            ->withCount('items');

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('origen_tipo') && $request->origen_tipo !== 'todos') {
            $query->where('origen_tipo', $request->origen_tipo);
        }

        if ($request->filled('oc_recibida_id')) {
            $query->where('oc_recibida_id', $request->integer('oc_recibida_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search): void {
                $query->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('ocRecibida', fn ($ocQuery) => $ocQuery
                        ->where('numero', 'like', "%{$search}%")
                        ->orWhere('cliente_nombre', 'like', "%{$search}%"))
                    ->orWhereHas('items', fn ($itemQuery) => $itemQuery
                        ->where('descripcion', 'like', "%{$search}%"));
            });
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 10))
        );
    }

    public function store(StoreRequerimientoCompraRequest $request, RequerimientoCompraService $service)
    {
        $requerimiento = $service->crearManual($request->validated(), $request);

        return response()->json([
            'message' => 'Requerimiento de compra registrado.',
            'requerimiento' => $requerimiento,
        ], 201);
    }

    public function show(RequerimientoCompra $requerimientoCompra)
    {
        return response()->json([
            'requerimiento' => $requerimientoCompra->load([
                'items.ocRecibidaItem',
                'items.cotizacionItem',
                'items.producto',
                'items.productoExterno',
                'ocRecibida',
                'solicitadoPor:id,nombres,apellidos,email',
                'asignadoA:id,nombres,apellidos,email',
            ]),
        ]);
    }

    public function faltantes(OcRecibida $ocRecibida, RequerimientoCompraService $service)
    {
        return response()->json([
            'oc_recibida' => $ocRecibida->only(['id', 'numero', 'cliente_nombre', 'estado', 'estado_logistico']),
            'faltantes' => $service->calcularFaltantesOc($ocRecibida),
        ]);
    }

    public function generarDesdeOc(
        GenerarRequerimientoDesdeOcRequest $request,
        OcRecibida $ocRecibida,
        RequerimientoCompraService $service
    ) {
        $requerimiento = $service->generarDesdeOc($ocRecibida, $request->validated(), $request);

        return response()->json([
            'message' => 'Requerimiento de compra generado desde faltantes reales.',
            'requerimiento' => $requerimiento,
        ], 201);
    }
}
