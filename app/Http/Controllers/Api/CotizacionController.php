<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cotizacion;
use App\Models\CotizacionItem;
use App\Models\Cliente;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\Moneda;
use App\Models\CotizacionCostosAdicional;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Services\CotizacionService;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;


class CotizacionController extends Controller
{
    protected CotizacionService $service;

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
            'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD',
            'moneda_id' => 'required|exists:monedas,id',
        ]);

        $numero = $this->service->generarNumero();
        $cliente = Cliente::findOrFail($request->cliente_id);

        $cotizacion = Cotizacion::create([
            'numero' => $numero,
            'fecha' => now(),
            'titulo' => $request->titulo ?? 'Cotizacion' . $numero,
            'tipo_cambio' => 1, // luego lo conectamos a API
            'validez_dias' => 10,

            'cliente_id' => $cliente->id,
            'plantilla_id' => $request->plantilla_id,
            'user_id' => $request->user()->id,
            'moneda_id' => $request->moneda_id,

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
            'message' => 'Cotización creada correctamente',
            'cotizacion' => $cotizacion
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'moneda_id' => 'required|exists:monedas,id',
            'modo_distribucion' => 'required|string',
        ]);

        DB::transaction(function () use ($request, $cotizacion) {
            $cotizacion->update($request->only([
                'cliente_id',
                'plantilla_id',
                'moneda_id',
                'modo_distribucion',
            ]));

            $this->service->recalcular($cotizacion);
        });

        return response()->json([
            'message' => 'Cotización actualizada correctamente',
            'cotizacion' => $cotizacion->load('items'),
        ]);
    }

    // =========================
    // 📦 ITEMS
    // =========================

    public function addItem(Request $request, int $cotizacionId)
    {
        $request->validate([
            'descripcion' => 'required|string',
            'cantidad' => 'required|numeric|min:1',
            'costo_base' => 'required|numeric|min:0',
            'margen' => 'required|numeric|min:0',
            'garantia_meses' => 'nullable|integer|in:3,6,12,24,36',
            'disponibilidad_tipo' => 'required|in:stock,importacion',
            'disponibilidad_dias' => 'required|integer|min:1|max:50',
            'producto_id' => 'nullable|exists:productos,id',
        ]);

        DB::transaction(function () use ($request, $cotizacionId) {

            $ultimoOrden = CotizacionItem::where('cotizacion_id', $cotizacionId)->max('orden') ?? 0;

            // Calcular valores iniciales para no violar NOT NULL
            $costoBase   = (float) $request->costo_base;
            $margen      = (float) $request->margen;
            $cantidad    = (int)   $request->cantidad;

            $precioVenta = $margen < 100
                ? round($costoBase / (1 - $margen / 100), 2)
                : $costoBase;

            $pvt = round($cantidad * $precioVenta, 2);
            $ptc = round($cantidad * $costoBase, 2);

            CotizacionItem::create([
                'cotizacion_id' => $cotizacionId,
                'descripcion' => $request->descripcion,
                'cantidad' => $cantidad,
                'costo_base' => $costoBase,
                'margen' => $margen,
                'marca' => $request->marca,
                'codigo' => $request->codigo,
                'unidad_medida' => $request->unidad_medida ?? 'UND',
                'garantia_meses' => $request->garantia_meses,
                'disponibilidad_tipo' => $request->disponibilidad_tipo,
                'disponibilidad_dias' => $request->disponibilidad_dias,
                'proveedor' => $request->proveedor,
                'link_proveedor' => $request->link_proveedor,
                'orden' => $ultimoOrden + 1,
                'producto_id' => $request->producto_id ?? null,
                'tipo' => $request->producto_id ? 'catalogo' : 'personalizado',

                // Valores calculados iniciales — recalcular() los refinará
                'costo_unitario'      => $costoBase,
                'precio_venta'        => $precioVenta,
                'subtotal'            => $pvt,
                'costo_total'         => $ptc,
                'ganancia'            => round($pvt - $ptc, 2),
            ]);

            $cotizacion = Cotizacion::findOrFail($cotizacionId);

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Item agregado']);
    }

    public function updateItem(Request $request, int $id)
    {
        $item = CotizacionItem::findOrFail($id);

        $request->validate([
            'descripcion' => 'nullable|string',
            'cantidad' => 'nullable|numeric|min:1',
            'costo_base' => 'nullable|numeric|min:0',
            'margen' => 'nullable|numeric|min:0',
            'garantia_meses' => 'nullable|integer|in:3,6,12,24,36',
            'disponibilidad_tipo' => 'nullable|in:stock,importacion',
            'disponibilidad_dias' => 'nullable|integer|min:1|max:50',
            'producto_id' => 'nullable|exists:productos,id',
        ]);

        DB::transaction(function () use ($request, $item) {

            $costoBase   = (float) ($request->costo_base   ?? $item->costo_base);
            $margen      = (float) ($request->margen       ?? $item->margen);
            $cantidad    = (int)   ($request->cantidad      ?? $item->cantidad);

            $precioVenta = $margen < 100
                ? round($costoBase / (1 - $margen / 100), 2)
                : $costoBase;

            $pvt = round($cantidad * $precioVenta, 2);
            $ptc = round($cantidad * $costoBase, 2);


            $item->update([
            ...$request->only([
                'descripcion', 'cantidad', 'costo_base', 'margen',
                'marca', 'codigo', 'unidad_medida', 'garantia_meses',
                'disponibilidad_tipo', 'disponibilidad_dias',
                'proveedor', 'link_proveedor', 'producto_id',
            ]),
            'costo_unitario' => $costoBase,
            'precio_venta'   => $precioVenta,
            'subtotal'       => $pvt,
            'costo_total'    => $ptc,
            'ganancia'       => round($pvt - $ptc, 2),
            'tipo'           => $item->producto_id ? 'producto' : ($request->tipo ?? $item->tipo),
        ]);

        $this->service->recalcular($item->cotizacion);
    });

        return response()->json(['message' => 'Item actualizado']);
    }

    public function deleteItem(int $id)
    {
        $item = CotizacionItem::findOrFail($id);

        DB::transaction(function () use ($item) {

            $cotizacion = $item->cotizacion;

            $item->delete();

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Item eliminado correctamente']);
    }

    public function desactivarItem(int $id)
    {
        $item = CotizacionItem::findOrFail($id);

        $item->update(['activo' => false]);

        return response()->json(['message' => 'Item desactivado']);
    }

    public function activarItem(int $id)
    {
        $item = CotizacionItem::findOrFail($id);

        $item->update(['activo' => true]);

        return response()->json(['message' => 'Item activado']);
    }

    public function indexItems(Request $request)
    {
        $query = CotizacionItem::query();
        if ($request->has('cotizacion_id')) {
            $query->where('cotizacion_id', $request->cotizacion_id);
        }
        return response()->json($query->latest()->paginate(10));
    }

    // =========================
    // 💰 COSTOS ADICIONALES
    // =========================

    public function addCosto(Request $request, int $cotizacionId)
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

    public function deleteCosto(int $id)
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

    public function recalcular(int $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $this->service->recalcular($cotizacion);

        return response()->json([
            'message' => 'Recalculado correctamente'
        ]);
    }

    // =========================
    // 🔥 EXPORTACION PDF
    // =========================
    public function exportarPdf(Cotizacion $cotizacion)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'items',
            'user.profile',
            'plantilla',
            'moneda'
        ])->findOrFail($cotizacion->id);

        //Validar plantilla:
        if (!$cotizacion->plantilla || !$cotizacion->plantilla->activo) {
            abort(404, 'Plantilla no disponible');
        }

        //Obtención formato PDF desde la plantilla
        $formatoPdf = $cotizacion->plantilla->formato_pdf;

        //Construir vista dinámica según formato
        $vista = 'pdf.cotizaciones.' . $formatoPdf;

        //Generar PDF usando la vista dinámica
        $pdf = Pdf::loadView($vista, compact('cotizacion'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => true
            ]); // Permitir cargar imágenes remotas

        // Vista previa en el navegador
        return $pdf->download(
            "COT.-{$cotizacion->numero}-{$cotizacion->cliente_nombre}-{$cotizacion->titulo}.pdf"
        );
    }

    // =========================
    // 📄 PLANTILLAS
    // =========================

    public function indexPlantillas(Request $request)
    {
        $query = Plantilla::query();

        if ($request->has('activo')) {
            $query->where('activo', $request->activo);
        }

        return response()->json($query->get());
    }

    // =========================
    // 📄 PLANTAFORMAS
    // =========================

    public function indexPlataformas(Request $request)
    {
            return Plataforma::all()->map(function ($plataforma) {
                    return [
                        'id' => $plataforma->id,
                        'nombre' => ucfirst($plataforma->nombre)
                    ];
                });
    }

    // =========================
    // 📄 ESTADO COTIZACION
    // =========================

    public function indexEstadoCotizacion(Request $request)
    {
        $query = EstadoCotizacion::query();
        return response()->json($query);
    }

    // =========================
    // 📄 ESTADO COTIZACION ITEM
    // =========================

    public function indexEstadoCotizacionItem(Request $request)
    {
        $query = EstadoCotizacionItem::query();
        return response()->json($query);
    }

    // =========================
    // 📄 MONEDA
    // =========================

    public function indexMonedas(Request $request)
    {
        $query = Moneda::query();
        return response()->json($query);
    }

    
    // =========================
    // COTIZACION COMPLETA
    // =========================
    public function storeCompleta(Request $request)
    {
        $request ->validate ([
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'plataforma_id' => 'required|exists:plataformas,id',
            'titulo' => 'required|string',
            'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD',
            'moneda_id' => 'required|exists:monedas,id',

            'items' => 'required|array|min:1',

            'items.*.descripcion' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.costo_base' => 'required|numeric|min:0',
            'items.*.margen' => 'required|numeric|min:0',

            'costos' => 'nullable|array',

            'costos.*.tipo' => 'required|string',
            'costos.*.monto' => 'required|numeric|min:0',
        ]);

        $cotizacion = null;

        DB::transaction(function() use ($request, &$cotizacion){
            $numero = $this->service->generarNumero();
            $cliente = Cliente::findOrFail($request->cliente_id);

            $cotizacion = Cotizacion::create([
            'numero' => $numero,
            'fecha' => now(),
            'titulo' => $request->titulo ?? 'Cotizacion' . $numero,
            'tipo_cambio' => 1, // luego lo conectamos a API
            'validez_dias' => 10,

            'cliente_id' => $cliente->id,
            'plantilla_id' => $request->plantilla_id,
            'user_id' => $request->user()->id,
            'moneda_id' => $request->moneda_id,

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

            

            //ITEMS
            foreach ($request->items as $index => $item){
                CotizacionItem::create([
                'cotizacion_id' => $cotizacion->id,
                'descripcion' => $item['descripcion'],
                'cantidad' => $item['cantidad'],
                'costo_base' => $item['costo_base'],
                'margen' => $item['margen'],
                'orden' => $index + 1,
                'marca' => $item['marca'],
                'codigo' =>$item['codigo'],
                'unidad_medida' => $item['unidad_medida'],
                'garantia_meses' => $item['garantia_meses'],
                'disponibilidad_tipo' => $item['disponibilidad_tipo'],
                'disponibilidad_dias' => $item['disponibilidad_dias'],
                'proveedor' => $item['proveedor'],
                'link_proveedor' => $item['link_proveedor'],
                'producto_id' => $item['producto_id'] ?? null,
                'tipo' => $item['tipo'] ?? 'personalizado',
                ]);
            }

            //COSTOS
            foreach ($request->costos as $costo){
                CotizacionCostosAdicional::create([
                'cotizacion_id' => $cotizacion->id,
                'tipo' => $costo['tipo'],
                'monto' => $costo['monto'],
                ]);
            }

            //RECALCULAR SOLO UNA VEZ
            $this->service->recalcular($cotizacion);
        });

        dd($cotizacion);

        return response()->json([
            'message'=> ' Cotización creada correctamente',
            'cotización' => $cotizacion->load([
                'items',
                'costosAdicionales',
                'cliente',
                'plantilla',
                'moneda'
            ]),
        ]);

    }
}
