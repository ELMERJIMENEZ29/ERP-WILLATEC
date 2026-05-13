<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\OrdenCompra;
use App\Services\OrdenCompraService;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    protected OrdenCompraService $service;

    public function __construct(
        OrdenCompraService $service)
    {
        $this->service = $service;
    }

    // =========================
    // LISTAR
    // =========================

    public function index()
    {
        $ordenes = OrdenCompra::with([
            'items',
            'cliente',
            'cotizacion'
        ])
        ->latest()
        ->paginate(10);

        return response()->json($ordenes);
    }

    // =========================
    // MOSTRAR
    // =========================

    public function show($id)
    {
        $orden = OrdenCompra::with([
            'items',
            'cliente',
            'cotizacion'
        ])->findOrFail($id);

        return response()->json($orden);
    }

    // =========================
    // CREAR DESDE COTIZACION
    // =========================

    public function store(Request $request)
    {
        $request->validate([
            'cotizacion_id' => 'required|exists:cotizaciones,id',

            'numero' => 'required|string|unique:orden_compras,numero',

            'items' => 'required|array',

            'items.*' => 'required|integer|min:1',

            'observaciones' => 'nullable|string',

            'fecha_entrega' => 'nullable|date',
        ]);

        $cotizacion = Cotizacion::with([
            'items',
            'plantilla'
        ])->findOrFail($request->cotizacion_id);

        $orden = $this->service->generarDesdeCotizacion(
            $cotizacion,
            $request->items,
            [
                'numero' => $request->numero,
                'observaciones' => $request->observaciones,
                'fecha_entrega' => $request->fecha_entrega,
            ]
        );

        return response()->json([
            'message' => 'Orden de compra creada correctamente',
            'orden_compra' => $orden
        ], 201);
    }

    // =========================
    // ACTUALIZAR ESTADO OC
    // =========================

    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,en_proceso,completado'
        ]);

        $orden = OrdenCompra::findOrFail($id);

        $orden->update([
            'estado' => $request->estado
        ]);

        return response()->json([
            'message' => 'Estado actualizado correctamente'
        ]);
    }

    // =========================
    // ACTUALIZAR ESTADO ITEM
    // =========================

    public function updateEstadoItem(
        Request $request,
        $id
    ) {

        $request->validate([
            'estado' => 'required|in:pendiente,comprado,entregado'
        ]);

        $item = \App\Models\OrdenCompraItem::findOrFail($id);

        $item->update([
            'estado' => $request->estado
        ]);

        return response()->json([
            'message' => 'Estado del item actualizado'
        ]);
    }
}
