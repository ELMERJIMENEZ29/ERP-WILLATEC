<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\Cliente;
use App\Models\CotizacionCostosAdicional;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;


class CotizacionController extends Controller
{
    protected $service;

    public function __construct(CotizacionService $service)
    {
        $this->service = $service;
    }

    // =========================
    // 📄 COTIZACIONES
    // =========================

    public function index(Request $request)
    {
        $query = Cotizacion::with(['cliente', 'estadoCotizacion']);

        if ($request->has('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        return response()->json($query->latest()->paginate(10));
    }

    public function show($id)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'items',
            'costosAdicionales',
            'estadoCotizacion',
        ])->find($id);

        if (!$cotizacion) {
            return response()->json([
                'message' => 'Cotización no encontrada'
            ], 404);
        }

        return response()->json($cotizacion);
    }

    public function store(Request $request)
    {
        $request->validate([
        'cliente_id' => 'required|exists:clientes,id',
        'plantilla_id' => 'required|exists:plantillas,id',
        'titulo' => 'required|string',
        'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD'
        ]);

        $numero = $this->service->generarNumero();
        $cliente = Cliente::findOrFail($request->cliente_id);

        $cotizacion = Cotizacion::create([
        'numero' => $numero,
        'fecha' => now(),
        'titulo' => $request->titulo ?? 'Cotizacion'.$numero,
        'tipo_cambio' => 1, // luego lo conectamos a API
        'validez_dias' => 10,

        'cliente_id' => $cliente->id,
        'plantilla_id' => $request->plantilla_id,
        'usuario_id' => $request->user()->id,

        'modo_distribucion' => $request->modo_distribucion ?? 'POR_ITEM',

        'subtotal' => 0,
        'igv' => 0,
        'total' => 0,

        // SNAPSHOT
        'cliente_nombre' => $cliente->nombre,
        'cliente_ruc' => $cliente->ruc,
        'cliente_contacto' => $cliente->contacto,
        'cliente_telefono' => $cliente->telefono,
        'cliente_correo' => $cliente->correo,

        // estado inicial (IMPORTANTE)
        'estado_cotizacion_id' => 1, // ej: borrador o enviada
        ]);

        return response()->json([
            'message'=> 'Cotización creada correctamente',
            'cotizacion' => $cotizacion
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'moneda' => 'required|in:PEN,USD',
            'modo_distribucion' => 'required|string',
        ]);

        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update($request->only([
                'cliente_id',
                'plantilla_id',
                'moneda',
                'modo_distribucion',
            ]));

            $this->service->recalcular($cotizacion);
        });

        return response()->json([
            'message'=> 'Cotización actualizada correctamente',
            'cotizacion' => $cotizacion->load('items'),
        ]);
    }

    // =========================
    // 📦 ITEMS
    // =========================

    public function addItem(Request $request, $cotizacionId)
    {
        $request->validate([
            'descripcion' => 'required|string',
            'cantidad' => 'required|numeric|min:1',
            'costo_base' => 'required|numeric|min:0',
            'margen' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $cotizacionId) {

        $ultimoOrden = CotizacionItem::where('cotizacion_id', $cotizacionId)->max('orden') ?? 0;

        $orden = $ultimoOrden + 1;

            CotizacionItem::create([
                'cotizacion_id' => $cotizacionId,
                'descripcion' => $request->descripcion,
                'cantidad' => $request->cantidad,
                'costo_base' => $request->costo_base,
                'margen' => $request->margen,
                'marca' => $request->marca,
                'codigo' => $request->codigo,
                'unidad_medida' => $request->unidad_medida,
                'disponibilidad' => $request->disponibilidad,
                'orden' => $orden,
            ]);

            $cotizacion = Cotizacion::findOrFail($cotizacionId);

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Item agregado']);
    }

    public function updateItem(Request $request, $id)
    {
        $item = CotizacionItem::findOrFail($id);

        DB::transaction(function () use ($request, $item) {

            $item->update($request->only([
                'descripcion',
                'cantidad',
                'costo_base',
                'margen',
                'marca',
                'codigo',
                'unidad_medida',
                'disponibilidad',
            ]));

            $this->service->recalcular($item->cotizacion);
        });

        return response()->json(['message' => 'Item actualizado']);
    }

    public function deleteItem($id)
    {
        $item = CotizacionItem::findOrFail($id);

        DB::transaction(function () use ($item) {

            $cotizacion = $item->cotizacion;

            $item->delete();

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Item eliminado correctamente']);
    }

    public function desactivarItem($id)
    {
        $item = CotizacionItem::findOrFail($id);

        $item->update(['activo' => false]);

        return response()->json(['message' => 'Item desactivado']);
    }

    public function activarItem($id)
    {
        $item = CotizacionItem::findOrFail($id);

        $item->update(['activo' => true]);

        return response()->json(['message' => 'Item activado']);
    }

    // =========================
    // 💰 COSTOS ADICIONALES
    // =========================

    public function addCosto(Request $request, $cotizacionId)
    {
        $request->validate([
            'tipo' => 'required|string',
            'monto' => 'required|numeric|min:0'
        ]);

        DB::transaction(function () use ($request, $cotizacionId) {

            CotizacionCostosAdicional::create([
                'cotizacion_id' => $cotizacionId,
                'tipo' => $request->tipo,
                'monto' => $request->monto,
            ]);

            $cotizacion = Cotizacion::findOrFail($cotizacionId);

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Costo agregado']);
    }

    public function deleteCosto($id)
    {
        $costo = CotizacionCostosAdicional::findOrFail($id);

        DB::transaction(function () use ($costo) {

            $cotizacion = $costo->cotizacion;

            $costo->delete();

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Costo eliminado']);
    }

    // =========================
    // 🔥 ACCIONES
    // =========================

    public function recalcular($id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $this->service->recalcular($cotizacion);

        return response()->json([
            'message' => 'Recalculado correctamente'
        ]);
    }
}
