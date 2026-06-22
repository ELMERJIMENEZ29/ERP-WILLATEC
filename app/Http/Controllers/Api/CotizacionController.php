<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionCostosAdicional;
use App\Models\CotizacionHistorial;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemProveedor;
use App\Models\CotizacionModificacion;
use App\Models\CotizacionVersion;
use App\Models\EstadoCotizacion;
use App\Models\EstadoCotizacionItem;
use App\Models\Moneda;
use App\Models\Plantilla;
use App\Models\Plataforma;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Models\User;
use App\Notifications\CotizacionAprobadaNotification;
use App\Notifications\CotizacionEnviadaRevisionNotification;
use App\Notifications\CotizacionModificacionEnRevisionNotification;
use App\Notifications\CotizacionModificacionSolicitadaNotification;
use App\Notifications\CotizacionRechazadaNotification;
use App\Services\CotizacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CotizacionController extends Controller
{
    private const BUSINESS_TIMEZONE = 'America/Lima';

    private const FORMAS_PAGO = [
        'AL CONTADO',
        'CRÉDITO 15 DÍAS',
        'CRÉDITO 30 DÍAS',
    ];

    protected CotizacionService $service;

    public function __construct(CotizacionService $service)
    {
        $this->service = $service;
    }

    private function notifySuperadmins(object $notification): void
    {
        User::role('superadmin')->get()->each->notify($notification);
    }

    // =========================
    // 📄 COTIZACIONES
    // =========================

    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Cotizacion::with([
            'cliente',
            'estadoCotizacion',
            'user',
            'delegado',
            'delegadoCotizacion',
        ])->withCount('items');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('titulo', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($clienteQuery) use ($search) {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('estado_cotizacion_id')) {
            $query->where('estado_cotizacion_id', $request->estado_cotizacion_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(
            $query
                ->latest()
                ->paginate($request->integer('per_page', 10))
        );
    }

    public function show($id)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'items.producto',
            'items.productoExterno',
            'items.proveedores',
            'costosAdicionales',
            'estadoCotizacion',
            'historial.estadoAnterior',
            'historial.estadoNuevo',
            'historial.usuario',
            'user',
            'delegado',
            'delegadoCotizacion',
        ])->find($id);

        if (! $cotizacion) {
            return response()->json([
                'message' => 'Cotización no encontrada',
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
            'delegado_id' => 'nullable|exists:users,id',
            'delegado_cotizacion_id' => 'nullable|exists:users,id',
            'validez_dias' => 'nullable|integer|min:1|max:365',
            'forma_pago' => 'nullable|in:'.implode(',', self::FORMAS_PAGO),
            'cliente_contacto' => 'nullable|string|max:255',
        ]);

        if ($request->filled('delegado_id') && ! $request->user()->hasRole('superadmin')) {
            abort(403, 'Solo superadmin puede delegar la aprobación.');
        }

        $this->ensureSalesUser($request->integer('delegado_id') ?: null);
        $this->ensureSalesUser($request->integer('delegado_cotizacion_id') ?: null);

        $numero = $this->service->generarNumero();
        $cliente = Cliente::findOrFail($request->cliente_id);

        $cotizacion = Cotizacion::create([
            'numero' => $numero,
            'fecha' => $this->todayBusinessDate(),
            'titulo' => $request->titulo ?? 'Cotizacion'.$numero,
            'tipo_cambio' => 1, // luego lo conectamos a API
            'validez_dias' => $request->integer('validez_dias') ?: 10,
            'forma_pago' => $request->forma_pago ?? 'AL CONTADO',

            'cliente_id' => $cliente->id,
            'plantilla_id' => $request->plantilla_id,
            'user_id' => $request->user()->id,
            'moneda_id' => $request->moneda_id,
            'delegado_id' => $request->delegado_id,
            'delegado_cotizacion_id' => $request->delegado_cotizacion_id,

            'modo_distribucion' => $request->modo_distribucion ?? 'POR_ITEM',

            'subtotal' => 0,
            'igv' => 0,
            'total' => 0,

            // SNAPSHOT
            'cliente_nombre' => $cliente->nombre,
            'cliente_ruc' => $cliente->ruc,
            'cliente_contacto' => $request->cliente_contacto,
            'cliente_telefono' => $cliente->telefono,
            'cliente_correo' => $cliente->correo,

            // estado inicial (IMPORTANTE)
            'estado_cotizacion_id' => $this->estadoCotizacionId('borrador'),
        ]);

        return response()->json([
            'message' => 'Cotización creada correctamente',
            'cotizacion' => $cotizacion,
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
            'delegado_id' => 'nullable|exists:users,id',
            'delegado_cotizacion_id' => 'nullable|exists:users,id',
            'forma_pago' => 'nullable|in:'.implode(',', self::FORMAS_PAGO),
            'cliente_contacto' => 'nullable|string|max:255',
        ]);

        $hasDelegadoKey = array_key_exists('delegado_id', $request->all());
        $hasDelegadoCotizacionKey = array_key_exists('delegado_cotizacion_id', $request->all());
        $requestedDelegadoId = $hasDelegadoKey
            ? ($request->filled('delegado_id') ? $request->integer('delegado_id') : null)
            : $cotizacion->delegado_id;
        $isChangingDelegado = $requestedDelegadoId !== ($cotizacion->delegado_id ? (int) $cotizacion->delegado_id : null);

        if ($isChangingDelegado && ! $request->user()->hasRole('superadmin')) {
            abort(403, 'Solo superadmin puede delegar la aprobación.');
        }

        if ($cotizacion->delegado_id && $isChangingDelegado) {
            abort(403, 'El delegado ya fue asignado para esta cotización y no puede cambiarse.');
        }

        $delegadoId = $requestedDelegadoId;
        $delegadoCotizacionId = $hasDelegadoCotizacionKey
            ? $request->delegado_cotizacion_id
            : $cotizacion->delegado_cotizacion_id;

        $this->ensureCanEditCotizacion($request, $cotizacion);
        $this->ensureSalesUser($delegadoId);
        $this->ensureSalesUser($delegadoCotizacionId);

        $estadoAnterior = (int) $cotizacion->estado_cotizacion_id;

        DB::transaction(function () use ($request, $cotizacion, $delegadoId, $delegadoCotizacionId, $estadoAnterior) {
            $cliente = Cliente::findOrFail($request->cliente_id);

            $cotizacion->update([
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $cliente->nombre,
                'cliente_ruc' => $cliente->ruc,
                'cliente_contacto' => $request->has('cliente_contacto')
                    ? $request->cliente_contacto
                    : $cotizacion->cliente_contacto,
                'cliente_telefono' => $cliente->telefono,
                'cliente_correo' => $cliente->correo,
                'plantilla_id' => $request->plantilla_id,
                'moneda_id' => $request->moneda_id,
                'modo_distribucion' => $request->modo_distribucion,
                'delegado_id' => $delegadoId,
                'delegado_cotizacion_id' => $delegadoCotizacionId,
                'forma_pago' => $request->forma_pago ?? $cotizacion->forma_pago,
            ]);

            $this->service->recalcular($cotizacion);
            $this->reenviarSiEstabaRechazada($request, $cotizacion, $estadoAnterior);
        });

        return response()->json([
            'message' => 'Cotización actualizada correctamente',
            'cotizacion' => $cotizacion->load('items.proveedores'),
        ]);
    }

    public function delegar(Request $request, Cotizacion $cotizacion)
    {
        $this->ensureCanDelegateApproval($request);

        $request->validate([
            'delegado_id' => 'required_without:delegado_cotizacion_id|nullable|exists:users,id',
            'delegado_cotizacion_id' => 'required_without:delegado_id|nullable|exists:users,id',
        ]);

        $delegadoId = $request->integer('delegado_id') ?: $request->integer('delegado_cotizacion_id');

        $this->ensureSalesUser($delegadoId);

        $cotizacion->update([
            'delegado_id' => $delegadoId,
        ]);

        return response()->json([
            'message' => 'Aprobación de cotización delegada correctamente',
            'cotizacion' => $cotizacion->refresh()->load(['cliente', 'estadoCotizacion', 'user', 'delegado', 'delegadoCotizacion']),
        ]);
    }

    public function enviarRevision(Request $request, Cotizacion $cotizacion)
    {
        $this->ensureCanEditCotizacion($request, $cotizacion);

        $estadoAnterior = $cotizacion->estado_cotizacion_id;
        $estadoEnviadaId = $this->estadoCotizacionId('enviada');

        DB::transaction(function () use ($request, $cotizacion, $estadoAnterior, $estadoEnviadaId) {
            $cotizacion->update([
                'estado_cotizacion_id' => $estadoEnviadaId,
            ]);

            if ((int) $estadoAnterior !== (int) $estadoEnviadaId) {
                CotizacionHistorial::create([
                    'cotizacion_id' => $cotizacion->id,
                    'estado_anterior_id' => $estadoAnterior,
                    'estado_nuevo_id' => $estadoEnviadaId,
                    'comentario' => $request->input('comentario'),
                    'user_id' => $request->user()->id,
                ]);
            }
        });

        $cotizacion->refresh()->load(['cliente', 'estadoCotizacion', 'user', 'delegado', 'delegadoCotizacion']);
        $this->notifySuperadmins(new CotizacionEnviadaRevisionNotification($cotizacion, $request->user()));

        return response()->json([
            'message' => 'Cotización enviada a revisión correctamente',
            'cotizacion' => $cotizacion->refresh()->load(['cliente', 'estadoCotizacion', 'user', 'delegado', 'delegadoCotizacion']),
        ]);
    }

    public function destroy(Request $request, Cotizacion $cotizacion)
    {
        $this->ensureCanEditCotizacion($request, $cotizacion);

        $cotizacion->load('items.proveedores');

        $imagePaths = $cotizacion->items
            ->pluck('imagen')
            ->filter()
            ->map(fn (string $path): ?string => $this->normalizePublicStoragePath($path))
            ->filter()
            ->values();

        DB::transaction(function () use ($cotizacion) {
            $cotizacion->delete();
        });

        $imagePaths->each(fn (string $path) => Storage::disk('public')->delete($path));

        return response()->json([
            'message' => 'Cotización eliminada correctamente',
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
            'aplica_costos_adicionales' => 'sometimes|boolean',
            'costo_base' => 'required|numeric|min:0',
            'margen' => 'required|numeric|min:0',
            'garantia_meses' => 'nullable|integer|in:3,6,12,24,36',
            'disponibilidad_tipo' => 'required|in:stock,importacion',
            'disponibilidad_dias' => 'required|integer|min:1|max:50',
            'producto_id' => 'nullable|exists:productos,id',
            'producto_externo_id' => 'nullable|exists:productos_externos,id',
            'proveedor' => 'nullable|string',
            'link_proveedor' => 'nullable|string',
            'tipo' => 'nullable|string|in:catalogo,personalizado,externo',
            'proveedores' => 'nullable|array',
            'proveedores.*.nombre' => 'required|string|max:255',
            'proveedores.*.link' => 'nullable|string',
            'proveedores.*.precio' => 'nullable|numeric|min:0',
            'proveedores.*.notas' => 'nullable|string',
            'imagen' => 'sometimes|nullable|image|max:2048',
            'imagen_path' => 'sometimes|nullable|string|max:2048',
        ]);

        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->ensureCanEditCotizacion($request, $cotizacion);

        DB::transaction(function () use ($request, $cotizacionId) {

            $ultimoOrden = CotizacionItem::where('cotizacion_id', $cotizacionId)->max('orden') ?? 0;

            // Calcular valores iniciales para no violar NOT NULL
            $costoBase = (float) $request->costo_base;
            $margen = (float) $request->margen;
            $cantidad = (int) $request->cantidad;

            $precioVenta = $margen < 100
                ? round($costoBase / (1 - $margen / 100), 2)
                : $costoBase;

            $pvt = round($cantidad * $precioVenta, 2);
            $ptc = round($cantidad * $costoBase, 2);

            $itemData = [
                'cotizacion_id' => $cotizacionId,
                'descripcion' => $request->descripcion,
                'cantidad' => $cantidad,
                'aplica_costos_adicionales' => $request->boolean('aplica_costos_adicionales', true),
                'costo_base' => $costoBase,
                'margen' => $margen,
                'marca' => $request->marca,
                'codigo' => $request->codigo,
                'unidad_medida' => $request->unidad_medida ?? 'UND',
                'garantia_meses' => $request->garantia_meses,
                'disponibilidad_tipo' => $request->disponibilidad_tipo,
                'disponibilidad_dias' => $request->disponibilidad_dias,
                'proveedor' => $this->primerProveedorNombre($request->input('proveedores'), $request->proveedor),
                'link_proveedor' => $this->primerProveedorLink($request->input('proveedores'), $request->link_proveedor),
                'orden' => $ultimoOrden + 1,
                'producto_id' => $request->producto_id ?? null,
                'producto_externo_id' => null,
                'tipo' => $request->tipo ?? ($request->producto_id ? 'catalogo' : 'personalizado'),

                // Valores calculados iniciales — recalcular() los refinará
                'costo_unitario' => $costoBase,
                'precio_venta' => $precioVenta,
                'subtotal' => $pvt,
                'costo_total' => $ptc,
                'ganancia' => round($pvt - $ptc, 2),
                'stock' => $request->stock ?? 0,
            ];

            if ($request->hasFile('imagen')) {
                $itemData['imagen'] = $request->file('imagen')->store('cotizacion-items', 'public');
            } elseif ($request->filled('imagen_path')) {
                $itemData['imagen'] = $this->normalizePublicStoragePath($request->string('imagen_path')->toString());
            } elseif ($request->filled('producto_id')) {
                $itemData['imagen'] = Producto::whereKey($request->integer('producto_id'))->value('imagen');
            }

            $itemData['producto_externo_id'] = $this->resolveProductoExternoId($itemData);

            $item = CotizacionItem::create($itemData);
            $this->syncItemProveedores($item, $request->input('proveedores'));

            $cotizacion = Cotizacion::findOrFail($cotizacionId);

            $this->service->recalcular($cotizacion);
        });

        return response()->json(['message' => 'Item agregado']);
    }

    public function updateItem(Request $request, int $id)
    {
        $item = CotizacionItem::findOrFail($id);
        $this->ensureCanEditCotizacion($request, $item->cotizacion);

        $request->validate([
            'descripcion' => 'nullable|string',
            'cantidad' => 'nullable|numeric|min:1',
            'aplica_costos_adicionales' => 'sometimes|boolean',
            'costo_base' => 'nullable|numeric|min:0',
            'margen' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'garantia_meses' => 'nullable|integer|in:3,6,12,24,36',
            'disponibilidad_tipo' => 'nullable|in:stock,importacion',
            'disponibilidad_dias' => 'nullable|integer|min:1|max:50',
            'producto_id' => 'nullable|exists:productos,id',
            'producto_externo_id' => 'nullable|exists:productos_externos,id',
            'proveedor' => 'nullable|string',
            'link_proveedor' => 'nullable|string',
            'tipo' => 'nullable|string|in:catalogo,personalizado,externo',
            'proveedores' => 'nullable|array',
            'proveedores.*.nombre' => 'required|string|max:255',
            'proveedores.*.link' => 'nullable|string',
            'proveedores.*.precio' => 'nullable|numeric|min:0',
            'proveedores.*.notas' => 'nullable|string',
            'imagen' => 'sometimes|nullable|image|max:2048',
        ]);

        DB::transaction(function () use ($request, $item) {

            $costoBase = (float) ($request->costo_base ?? $item->costo_base);
            $margen = (float) ($request->margen ?? $item->margen);
            $cantidad = (int) ($request->cantidad ?? $item->cantidad);

            $precioVenta = $margen < 100
                ? round($costoBase / (1 - $margen / 100), 2)
                : $costoBase;

            $pvt = round($cantidad * $precioVenta, 2);
            $ptc = round($cantidad * $costoBase, 2);

            $itemData = [
                ...$request->only([
                    'descripcion',
                    'cantidad',
                    'aplica_costos_adicionales',
                    'costo_base',
                    'margen',
                    'marca',
                    'codigo',
                    'unidad_medida',
                    'garantia_meses',
                    'disponibilidad_tipo',
                    'disponibilidad_dias',
                    'proveedor',
                    'link_proveedor',
                    'producto_id',
                    'producto_externo_id',
                    'stock',
                    'tipo',
                ]),
                'costo_unitario' => $costoBase,
                'precio_venta' => $precioVenta,
                'subtotal' => $pvt,
                'costo_total' => $ptc,
                'ganancia' => round($pvt - $ptc, 2),
                'tipo' => $request->tipo ?? $item->tipo,
            ];

            if ($request->hasFile('imagen')) {
                if ($item->imagen) {
                    Storage::disk('public')->delete($item->imagen);
                }

                $itemData['imagen'] = $request->file('imagen')->store('cotizacion-items', 'public');
            } elseif ($request->filled('imagen_path')) {
                $itemData['imagen'] = $this->normalizePublicStoragePath($request->string('imagen_path')->toString());
            }

            if ($request->has('proveedores')) {
                $itemData['proveedor'] = $this->primerProveedorNombre($request->input('proveedores'), $itemData['proveedor'] ?? null);
                $itemData['link_proveedor'] = $this->primerProveedorLink($request->input('proveedores'), $itemData['link_proveedor'] ?? null);
            }

            $snapshotForProductoExterno = [
                ...$item->toArray(),
                ...$itemData,
            ];
            $itemData['producto_externo_id'] = $this->resolveProductoExternoId($snapshotForProductoExterno);

            $item->update($itemData);
            $this->syncItemProveedores($item, $request->input('proveedores'));

            $this->service->recalcular($item->cotizacion);
        });

        return response()->json(['message' => 'Item actualizado']);
    }

    public function deleteItem(Request $request, int $id)
    {
        $item = CotizacionItem::findOrFail($id);
        $this->ensureCanEditCotizacion($request, $item->cotizacion);

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
        $query = CotizacionItem::with('proveedores');
        if ($request->has('cotizacion_id')) {
            $query->where('cotizacion_id', $request->cotizacion_id);
        }

        return response()->json($query->latest()->paginate(10));
    }

    public function allItems(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CotizacionItem::with('proveedores');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search): void {
                $query->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('proveedor', 'like', "%{$search}%")
                    ->orWhereHas('proveedores', function ($query) use ($search): void {
                        $query->where('nombre', 'like', "%{$search}%")
                            ->orWhere('link', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 10)));
    }

    // =========================
    // 💰 COSTOS ADICIONALES
    // =========================

    public function addCosto(Request $request, int $cotizacionId)
    {
        $request->validate([
            'tipo' => 'required|string',
            'monto' => 'required|numeric|min:0',
        ]);

        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->ensureCanEditCotizacion($request, $cotizacion);

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

    public function deleteCosto(Request $request, int $id)
    {
        $costo = CotizacionCostosAdicional::findOrFail($id);
        $this->ensureCanEditCotizacion($request, $costo->cotizacion);

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

    public function recalcular(Request $request, int $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);
        $this->ensureCanEditCotizacion($request, $cotizacion);

        $this->service->recalcular($cotizacion);

        return response()->json([
            'message' => 'Recalculado correctamente',
        ]);
    }

    public function historial(Request $request, int $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $historial = CotizacionHistorial::where('cotizacion_id', $id)
            ->with([
                'estadoAnterior',
                'estadoNuevo',
                'usuario:id,nombres,apellidos,email',
            ])
            ->latest()
            ->paginate($request->per_page ?? 15);

        return response()->json($historial);
    }

    // =========================
    // 🔥 EXPORTACION PDF
    // =========================
    public function versiones(Request $request, Cotizacion $cotizacion)
    {
        return response()->json([
            'cotizacion_id' => $cotizacion->id,
            'numero' => $cotizacion->numero,
            'version_vigente' => $cotizacion->versiones()->max('version_number') ?: 1,
            'versiones' => $cotizacion->versiones()
                ->with(['creador:id,nombres,apellidos,email', 'aprobador:id,nombres,apellidos,email'])
                ->get(),
            'modificaciones' => $cotizacion->modificaciones()
                ->with(['solicitante:id,nombres,apellidos,email', 'revisor:id,nombres,apellidos,email'])
                ->latest()
                ->get(),
        ]);
    }

    public function solicitarModificacion(Request $request, Cotizacion $cotizacion)
    {
        $this->ensureCanRequestModification($request, $cotizacion);
        $this->ensureCotizacionIsApproved($cotizacion);

        $request->validate([
            'motivo' => 'required|string|max:1000',
        ]);

        $modificacion = null;

        DB::transaction(function () use ($request, $cotizacion, &$modificacion) {
            $versionOriginal = $this->ensureCotizacionVersionSnapshot(
                $cotizacion,
                $request->user()->id,
                'Version original antes de solicitud de modificacion.'
            );
            $versionVigente = $this->latestCotizacionVersion($cotizacion) ?? $versionOriginal;

            $pendiente = $cotizacion->modificaciones()
                ->whereIn('estado', [
                    CotizacionModificacion::ESTADO_BORRADOR,
                    CotizacionModificacion::ESTADO_EN_REVISION,
                ])
                ->lockForUpdate()
                ->first();

            if ($pendiente) {
                abort(422, 'Ya existe una modificacion pendiente para esta cotizacion.');
            }

            $modificacion = CotizacionModificacion::create([
                'cotizacion_id' => $cotizacion->id,
                'original_version_id' => $versionVigente->id,
                'version_number' => $versionVigente->version_number + 1,
                'estado' => CotizacionModificacion::ESTADO_BORRADOR,
                'motivo' => $request->string('motivo')->toString(),
                'propuesta' => $this->buildCotizacionEditablePayload($cotizacion),
                'requested_by' => $request->user()->id,
            ]);
        });

        if ($modificacion) {
            $modificacion->load('cotizacion');
            $this->notifySuperadmins(new CotizacionModificacionSolicitadaNotification($modificacion, $request->user()));
        }

        return response()->json([
            'message' => 'Solicitud de modificacion creada correctamente',
            'modificacion' => $modificacion?->load(['cotizacion', 'originalVersion', 'solicitante']),
        ], 201);
    }

    public function showModificacion(Request $request, CotizacionModificacion $modificacion)
    {
        $this->ensureCanViewModification($request, $modificacion);

        return response()->json(
            $modificacion->load([
                'cotizacion.estadoCotizacion',
                'originalVersion',
                'solicitante:id,nombres,apellidos,email',
                'revisor:id,nombres,apellidos,email',
            ])
        );
    }

    public function updateModificacion(Request $request, CotizacionModificacion $modificacion)
    {
        $this->ensureCanEditModification($request, $modificacion);

        $payload = $this->buildCotizacionProposalPayload($request, $modificacion->cotizacion);

        $modificacion->update([
            'estado' => CotizacionModificacion::ESTADO_EN_REVISION,
            'propuesta' => $payload,
            'submitted_at' => now(),
        ]);

        $modificacion->refresh()->load('cotizacion');
        $this->notifySuperadmins(new CotizacionModificacionEnRevisionNotification($modificacion, $request->user()));

        return response()->json([
            'message' => 'Modificacion guardada y enviada a revision',
            'modificacion' => $modificacion->refresh()->load(['cotizacion', 'solicitante']),
        ]);
    }

    public function enviarModificacionRevision(Request $request, CotizacionModificacion $modificacion)
    {
        $this->ensureCanEditModification($request, $modificacion);

        $modificacion->update([
            'estado' => CotizacionModificacion::ESTADO_EN_REVISION,
            'submitted_at' => now(),
        ]);

        $modificacion->refresh()->load('cotizacion');
        $this->notifySuperadmins(new CotizacionModificacionEnRevisionNotification($modificacion, $request->user()));

        return response()->json([
            'message' => 'Modificacion enviada a revision',
            'modificacion' => $modificacion->refresh()->load(['cotizacion', 'solicitante']),
        ]);
    }

    public function aprobarModificacion(Request $request, CotizacionModificacion $modificacion)
    {
        $this->ensureCanReviewModification($request);

        if ($modificacion->estado !== CotizacionModificacion::ESTADO_EN_REVISION) {
            abort(422, 'Solo se pueden aprobar modificaciones en revision.');
        }

        $version = null;

        DB::transaction(function () use ($request, $modificacion, &$version) {
            $cotizacion = $modificacion->cotizacion()->lockForUpdate()->firstOrFail();
            $this->ensureCotizacionIsApproved($cotizacion);
            $this->ensureCotizacionVersionSnapshot(
                $cotizacion,
                $modificacion->requested_by,
                'Version original antes de aprobar modificacion.'
            );
            $nextVersionNumber = ($this->latestCotizacionVersion($cotizacion)?->version_number ?? 1) + 1;

            $this->applyCotizacionProposalPayload($request, $cotizacion, $modificacion->propuesta);

            $cotizacion->update([
                'estado_cotizacion_id' => $this->estadoCotizacionId('aprobada'),
            ]);

            $version = CotizacionVersion::create([
                'cotizacion_id' => $cotizacion->id,
                'version_number' => $nextVersionNumber,
                'numero_version' => $this->numeroVersion($cotizacion, $nextVersionNumber),
                'snapshot' => $this->buildCotizacionSnapshot($cotizacion->refresh()),
                'created_by' => $modificacion->requested_by,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notas' => $modificacion->motivo,
            ]);

            $modificacion->update([
                'estado' => CotizacionModificacion::ESTADO_APROBADA,
                'version_number' => $nextVersionNumber,
                'reviewed_by' => $request->user()->id,
                'comentario_revision' => $request->input('comentario_revision'),
                'reviewed_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Modificacion aprobada y aplicada como nueva version vigente',
            'version' => $version,
            'cotizacion' => $modificacion->cotizacion->refresh()->load([
                'items.proveedores',
                'costosAdicionales',
                'cliente',
                'estadoCotizacion',
                'user',
                'delegado',
                'delegadoCotizacion',
            ]),
        ]);
    }

    public function rechazarModificacion(Request $request, CotizacionModificacion $modificacion)
    {
        $this->ensureCanReviewModification($request);

        if ($modificacion->estado !== CotizacionModificacion::ESTADO_EN_REVISION) {
            abort(422, 'Solo se pueden rechazar modificaciones en revision.');
        }

        $request->validate([
            'comentario_revision' => 'required|string|max:1000',
        ]);

        $modificacion->update([
            'estado' => CotizacionModificacion::ESTADO_RECHAZADA,
            'reviewed_by' => $request->user()->id,
            'comentario_revision' => $request->string('comentario_revision')->toString(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Modificacion rechazada; se conserva la version aprobada vigente',
            'modificacion' => $modificacion->refresh()->load(['cotizacion', 'solicitante', 'revisor']),
        ]);
    }

    public function exportarPdf(Cotizacion $cotizacion)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'items.producto',
            'items.productoExterno',
            'items.proveedores',
            'user.profile',
            'plantilla',
            'moneda',
        ])->findOrFail($cotizacion->id);

        // Validar plantilla:
        if (! $cotizacion->plantilla || ! $cotizacion->plantilla->activo) {
            abort(404, 'Plantilla no disponible');
        }

        $vista = $this->resolveCotizacionPdfView((string) $cotizacion->plantilla->formato_pdf);

        // Generar PDF usando la vista dinámica
        $pdf = Pdf::loadView($vista, compact('cotizacion'))
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isLocalEnabled' => true,  // ← agrega esto
                'chroot' => public_path(), // ← restringe acceso a public/

            ]); // Permitir cargar imágenes remotas

        // Vista previa en el navegador
        $filename = $this->buildCotizacionPdfFilename($cotizacion);

        return $pdf->download($filename)
            ->header('X-Suggested-Filename', $filename);
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
                'nombre' => ucfirst($plataforma->nombre),
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
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'plataforma_id' => 'required|exists:plataformas,id',
            'titulo' => 'required|string',
            'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD',
            'moneda_id' => 'required|exists:monedas,id',
            'delegado_id' => 'nullable|exists:users,id',
            'delegado_cotizacion_id' => 'nullable|exists:users,id',
            'validez_dias' => 'nullable|integer|min:1|max:365',
            'estado_cotizacion_id' => 'nullable|exists:estado_cotizaciones,id',
            'forma_pago' => 'nullable|in:'.implode(',', self::FORMAS_PAGO),
            'cliente_contacto' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',

            'items.*.descripcion' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.aplica_costos_adicionales' => 'sometimes|boolean',
            'items.*.costo_base' => 'required|numeric|min:0',
            'items.*.margen' => 'required|numeric|min:0',
            'items.*.tipo' => 'nullable|string|in:catalogo,personalizado,externo',
            'items.*.producto_externo_id' => 'nullable|exists:productos_externos,id',
            'items.*.imagen' => 'sometimes|nullable',
            'items.*.proveedores' => 'nullable|array',
            'items.*.proveedores.*.nombre' => 'required|string|max:255',
            'items.*.proveedores.*.link' => 'nullable|string',
            'items.*.proveedores.*.precio' => 'nullable|numeric|min:0',
            'items.*.proveedores.*.notas' => 'nullable|string',

            'costos' => 'nullable|array',

            'costos.*.tipo' => 'required|string',
            'costos.*.monto' => 'required|numeric|min:0',
        ]);

        if ($request->filled('delegado_id') && ! $request->user()->hasRole('superadmin')) {
            abort(403, 'Solo superadmin puede delegar la aprobación.');
        }

        $this->ensureSalesUser($request->integer('delegado_id') ?: null);
        $this->ensureSalesUser($request->integer('delegado_cotizacion_id') ?: null);

        $cotizacion = null;

        DB::transaction(function () use ($request, &$cotizacion) {
            $numero = $this->service->generarNumero();
            $cliente = Cliente::findOrFail($request->cliente_id);

            $cotizacion = Cotizacion::create([
                'numero' => $numero,
                'fecha' => $this->todayBusinessDate(),
                'titulo' => $request->titulo ?? 'Cotizacion'.$numero,
                'tipo_cambio' => 1, // luego lo conectamos a API
                'validez_dias' => $request->integer('validez_dias') ?: 10,
                'forma_pago' => $request->forma_pago ?? 'AL CONTADO',

                'cliente_id' => $cliente->id,
                'plantilla_id' => $request->plantilla_id,
                'plataforma_id' => $request->plataforma_id,
                'user_id' => $request->user()->id,
                'moneda_id' => $request->moneda_id,
                'delegado_id' => $request->delegado_id,
                'delegado_cotizacion_id' => $request->delegado_cotizacion_id,

                'modo_distribucion' => $request->modo_distribucion ?? 'POR_ITEM',

                'subtotal' => 0,
                'igv' => 0,
                'total' => 0,

                // SNAPSHOT
                'cliente_nombre' => $cliente->nombre,
                'cliente_ruc' => $cliente->ruc,
                'cliente_contacto' => $request->cliente_contacto,
                'cliente_telefono' => $cliente->telefono,
                'cliente_correo' => $cliente->correo,

                // estado inicial (IMPORTANTE)
                'estado_cotizacion_id' => $this->estadoCotizacionId('borrador'),
            ]);

            // ITEMS
            foreach ($request->items as $index => $item) {
                $costoBase = (float) ($item['costo_base'] ?? 0);
                $margen = min((float) ($item['margen'] ?? 0), 99.99);
                $cantidad = (int) ($item['cantidad'] ?? 1);

                $factorMargen = $margen < 100 ? 1 - ($margen / 100) : 0.0001;
                $precioVenta = round($costoBase / $factorMargen, 2);
                $pvt = round($cantidad * $precioVenta, 2);
                $ptc = round($cantidad * $costoBase, 2);
                $itemData = [
                    'cotizacion_id' => $cotizacion->id,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'aplica_costos_adicionales' => $item['aplica_costos_adicionales'] ?? true,
                    'costo_base' => $item['costo_base'],
                    'margen' => $item['margen'],
                    'orden' => $index + 1,
                    'marca' => $item['marca'] ?? null,
                    'codigo' => $item['codigo'] ?? null,
                    'unidad_medida' => $item['unidad_medida'] ?? 'UND',
                    'garantia_meses' => $item['garantia_meses'] ?? null,
                    'disponibilidad_tipo' => $item['disponibilidad_tipo'] ?? 'stock',
                    'disponibilidad_dias' => $item['disponibilidad_dias'] ?? null,
                    'proveedor' => $this->primerProveedorNombre($item['proveedores'] ?? null, $item['proveedor'] ?? null),
                    'link_proveedor' => $this->primerProveedorLink($item['proveedores'] ?? null, $item['link_proveedor'] ?? null),
                    'producto_id' => $item['producto_id'] ?? null,
                    'tipo' => $item['tipo'] ?? 'personalizado',
                    'imagen' => $this->resolveCotizacionItemImagen($request, $index, $item),

                    // Valores calculados iniciales — recalcular() los refinará con costos adicionales
                    'costo_unitario' => $costoBase,
                    'precio_venta' => $precioVenta,
                    'subtotal' => $pvt,
                    'costo_total' => $ptc,
                    'ganancia' => round($pvt - $ptc, 2),
                    'stock' => 0,
                    'delegado_id' => $cotizacion->delegado_id,
                ];

                $itemData['producto_externo_id'] = $this->resolveProductoExternoId($itemData);

                $cotizacionItem = CotizacionItem::create($itemData);

                $this->syncItemProveedores($cotizacionItem, $item['proveedores'] ?? null);
            }

            // COSTOS
            foreach ($request->costos ?? [] as $costo) {
                CotizacionCostosAdicional::create([
                    'cotizacion_id' => $cotizacion->id,
                    'tipo' => $costo['tipo'],
                    'descripcion' => $costo['descripcion'] ?? null,
                    'monto' => $costo['monto'],
                ]);
            }

            // RECALCULAR SOLO UNA VEZ
            $this->service->recalcular($cotizacion);
        });

        if (! $cotizacion) {
            return response()->json([
                'message' => 'Error al crear cotización',
            ], 500);
        }

        return response()->json([
            'message' => ' Cotizacion creada correctamente',
            'cotizacion' => $cotizacion->load([
                'items.productoExterno',
                'items.proveedores',
                'costosAdicionales',
                'cliente',
                'plantilla',
                'moneda',
                'delegadoCotizacion',
            ]),
        ]);
    }

    public function updateCompleta(Request $request, int $id)
    {
        $cotizacion = Cotizacion::findOrFail($id);

        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'plataforma_id' => 'required|exists:plataformas,id',
            'titulo' => 'required|string',
            'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD',
            'moneda_id' => 'required|exists:monedas,id',
            'delegado_id' => 'nullable|exists:users,id',
            'delegado_cotizacion_id' => 'nullable|exists:users,id',
            'validez_dias' => 'nullable|integer|min:1|max:365',
            'estado_cotizacion_id' => 'nullable|exists:estado_cotizaciones,id',
            'forma_pago' => 'nullable|in:'.implode(',', self::FORMAS_PAGO),
            'cliente_contacto' => 'nullable|string|max:255',

            'items' => 'required|array|min:1',

            'items.*.descripcion' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.aplica_costos_adicionales' => 'sometimes|boolean',
            'items.*.costo_base' => 'required|numeric|min:0',
            'items.*.margen' => 'required|numeric|min:0',
            'items.*.tipo' => 'nullable|string|in:catalogo,personalizado,externo',
            'items.*.producto_externo_id' => 'nullable|exists:productos_externos,id',
            'items.*.imagen' => 'sometimes|nullable',
            'items.*.proveedores' => 'nullable|array',
            'items.*.proveedores.*.nombre' => 'required|string|max:255',
            'items.*.proveedores.*.link' => 'nullable|string',
            'items.*.proveedores.*.precio' => 'nullable|numeric|min:0',
            'items.*.proveedores.*.notas' => 'nullable|string',

            'costos' => 'nullable|array',

            'costos.*.tipo' => 'required|string',
            'costos.*.monto' => 'required|numeric|min:0',
        ]);

        $cliente = Cliente::findOrFail($request->cliente_id);
        $hasDelegadoKey = array_key_exists('delegado_id', $request->all());
        $hasDelegadoCotizacionKey = array_key_exists('delegado_cotizacion_id', $request->all());
        $requestedDelegadoId = $hasDelegadoKey
            ? ($request->filled('delegado_id') ? $request->integer('delegado_id') : null)
            : $cotizacion->delegado_id;
        $isChangingDelegado = $requestedDelegadoId !== ($cotizacion->delegado_id ? (int) $cotizacion->delegado_id : null);

        if ($isChangingDelegado && ! $request->user()->hasRole('superadmin')) {
            abort(403, 'Solo superadmin puede delegar la aprobación.');
        }

        if ($cotizacion->delegado_id && $isChangingDelegado) {
            abort(403, 'El delegado ya fue asignado para esta cotización y no puede cambiarse.');
        }

        $delegadoId = $requestedDelegadoId;
        $delegadoCotizacionId = $hasDelegadoCotizacionKey
            ? $request->delegado_cotizacion_id
            : $cotizacion->delegado_cotizacion_id;

        $this->ensureCanEditCotizacion($request, $cotizacion);
        $this->ensureSalesUser($delegadoId);
        $this->ensureSalesUser($delegadoCotizacionId);

        $estadoAnterior = (int) $cotizacion->estado_cotizacion_id;

        DB::transaction(function () use ($request, $cotizacion, $cliente, $delegadoId, $delegadoCotizacionId, $estadoAnterior) {
            // UPDATE HEADER
            $cotizacion->update([
                'cliente_id' => $cliente->id,
                'cliente_nombre' => $cliente->nombre,
                'cliente_ruc' => $cliente->ruc,
                'cliente_contacto' => $request->has('cliente_contacto')
                    ? $request->cliente_contacto
                    : $cotizacion->cliente_contacto,
                'cliente_telefono' => $cliente->telefono,
                'cliente_correo' => $cliente->correo,
                'plantilla_id' => $request->plantilla_id,
                'plataforma_id' => $request->plataforma_id,
                'moneda_id' => $request->moneda_id,
                'modo_distribucion' => $request->modo_distribucion,
                'titulo' => $request->titulo,
                'validez_dias' => $request->integer('validez_dias') ?: $cotizacion->validez_dias,
                'subtotal' => 0,
                'igv' => 0,
                'total' => 0,
                'estado_cotizacion_id' => $cotizacion->estado_cotizacion_id,
                'delegado_id' => $delegadoId,
                'delegado_cotizacion_id' => $delegadoCotizacionId,
                'forma_pago' => $request->forma_pago ?? $cotizacion->forma_pago,
            ]);

            // ELIMINAR SNAPSHOT VIEJO
            $cotizacion->items()->delete();
            $cotizacion->costosAdicionales()->delete();

            // RECREAR ITEMS
            foreach ($request->items as $index => $item) {
                $costoBase = (float) ($item['costo_base'] ?? 0);
                $margen = min((float) ($item['margen'] ?? 0), 99.99);
                $cantidad = (int) ($item['cantidad'] ?? 1);

                $factorMargen = $margen < 100 ? 1 - ($margen / 100) : 0.0001;
                $precioVenta = round($costoBase / $factorMargen, 2);
                $pvt = round($cantidad * $precioVenta, 2);
                $ptc = round($cantidad * $costoBase, 2);
                $itemData = [
                    'cotizacion_id' => $cotizacion->id,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'aplica_costos_adicionales' => $item['aplica_costos_adicionales'] ?? true,
                    'costo_base' => $item['costo_base'],
                    'margen' => $item['margen'],
                    'orden' => $index + 1,
                    'marca' => $item['marca'] ?? null,
                    'codigo' => $item['codigo'] ?? null,
                    'unidad_medida' => $item['unidad_medida'] ?? 'UND',
                    'garantia_meses' => $item['garantia_meses'] ?? null,
                    'disponibilidad_tipo' => $item['disponibilidad_tipo'] ?? 'stock',
                    'disponibilidad_dias' => $item['disponibilidad_dias'] ?? null,
                    'proveedor' => $this->primerProveedorNombre($item['proveedores'] ?? null, $item['proveedor'] ?? null),
                    'link_proveedor' => $this->primerProveedorLink($item['proveedores'] ?? null, $item['link_proveedor'] ?? null),
                    'producto_id' => $item['producto_id'] ?? null,
                    'tipo' => $item['tipo'] ?? 'personalizado',
                    'imagen' => $this->resolveCotizacionItemImagen($request, $index, $item),

                    // Valores calculados iniciales — recalcular() los refinará con costos adicionales
                    'costo_unitario' => $costoBase,
                    'precio_venta' => $precioVenta,
                    'subtotal' => $pvt,
                    'costo_total' => $ptc,
                    'ganancia' => round($pvt - $ptc, 2),
                    'stock' => 0,
                ];

                $itemData['producto_externo_id'] = $this->resolveProductoExternoId($itemData);

                $cotizacionItem = CotizacionItem::create($itemData);

                $this->syncItemProveedores($cotizacionItem, $item['proveedores'] ?? null);
            }

            // RECREAR COSTOS
            foreach ($request->costos ?? [] as $costo) {
                CotizacionCostosAdicional::create([
                    'cotizacion_id' => $cotizacion->id,
                    'tipo' => $costo['tipo'],
                    'descripcion' => $costo['descripcion'] ?? null,
                    'monto' => $costo['monto'],
                ]);
            }

            // RECALCULAR
            $this->service->recalcular($cotizacion);
            $this->reenviarSiEstabaRechazada($request, $cotizacion, $estadoAnterior);

            $cotizacion->refresh();
        });

        return response()->json([
            'message' => 'Cotización actualizada',
            'cotizacion' => $cotizacion->load([
                'items.productoExterno',
                'items.proveedores',
                'costosAdicionales',
                'cliente',
                'plantilla',
                'moneda',
                'delegadoCotizacion',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCotizacionProposalPayload(Request $request, Cotizacion $cotizacion): array
    {
        $data = $request->validate($this->cotizacionProposalRules());
        $cliente = Cliente::findOrFail($data['cliente_id']);

        $data['cliente_nombre'] = $cliente->nombre;
        $data['cliente_ruc'] = $cliente->ruc;
        $data['cliente_contacto'] = array_key_exists('cliente_contacto', $data)
            ? $data['cliente_contacto']
            : $cotizacion->cliente_contacto;
        $data['cliente_telefono'] = $cliente->telefono;
        $data['cliente_correo'] = $cliente->correo;

        foreach ($data['items'] as $index => $item) {
            $item['imagen'] = $this->resolveCotizacionItemImagen($request, $index, $item);
            $item['proveedor'] = $this->primerProveedorNombre($item['proveedores'] ?? null, $item['proveedor'] ?? null);
            $item['link_proveedor'] = $this->primerProveedorLink($item['proveedores'] ?? null, $item['link_proveedor'] ?? null);
            $data['items'][$index] = $item;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCotizacionSnapshot(Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing([
            'items.proveedores',
            'costosAdicionales',
            'cliente',
            'plantilla',
            'moneda',
            'estadoCotizacion',
            'user',
            'delegado',
            'delegadoCotizacion',
        ]);

        return [
            'cotizacion' => $cotizacion->only([
                'id',
                'numero',
                'fecha',
                'validez_dias',
                'forma_pago',
                'tipo_cambio',
                'titulo',
                'modo_distribucion',
                'moneda_id',
                'subtotal',
                'igv',
                'total',
                'ganancia',
                'total_gasto',
                'cliente_id',
                'plantilla_id',
                'estado_cotizacion_id',
                'user_id',
                'plataforma_id',
                'cliente_nombre',
                'cliente_ruc',
                'cliente_contacto',
                'cliente_telefono',
                'cliente_correo',
                'delegado_id',
                'delegado_cotizacion_id',
            ]),
            'items' => $cotizacion->items
                ->sortBy('orden')
                ->values()
                ->map(fn (CotizacionItem $item): array => [
                    ...$item->only([
                        'descripcion',
                        'cantidad',
                        'aplica_costos_adicionales',
                        'marca',
                        'codigo',
                        'unidad_medida',
                        'disponibilidad',
                        'costo_unitario',
                        'costo_base',
                        'margen',
                        'precio_venta',
                        'subtotal',
                        'imagen',
                        'orden',
                        'producto_id',
                        'producto_externo_id',
                        'tipo',
                        'estado_cotizacion_item_id',
                        'costo_total',
                        'ganancia',
                        'garantia_meses',
                        'disponibilidad_tipo',
                        'disponibilidad_dias',
                        'proveedor',
                        'link_proveedor',
                        'stock',
                    ]),
                    'proveedores' => $item->proveedores
                        ->sortBy('orden')
                        ->values()
                        ->map(fn (CotizacionItemProveedor $proveedor): array => $proveedor->only([
                            'nombre',
                            'link',
                            'precio',
                            'notas',
                            'orden',
                        ]))
                        ->all(),
                ])
                ->all(),
            'costos' => $cotizacion->costosAdicionales
                ->map(fn (CotizacionCostosAdicional $costo): array => $costo->only([
                    'tipo',
                    'descripcion',
                    'monto',
                ]))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCotizacionEditablePayload(Cotizacion $cotizacion): array
    {
        $snapshot = $this->buildCotizacionSnapshot($cotizacion);
        $header = $snapshot['cotizacion'];

        return [
            'cliente_id' => $header['cliente_id'],
            'plantilla_id' => $header['plantilla_id'],
            'plataforma_id' => $header['plataforma_id'],
            'titulo' => $header['titulo'],
            'modo_distribucion' => $header['modo_distribucion'],
            'moneda_id' => $header['moneda_id'],
            'delegado_id' => $header['delegado_id'],
            'delegado_cotizacion_id' => $header['delegado_cotizacion_id'],
            'validez_dias' => $header['validez_dias'],
            'forma_pago' => $header['forma_pago'],
            'cliente_contacto' => $header['cliente_contacto'],
            'items' => $snapshot['items'],
            'costos' => $snapshot['costos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyCotizacionProposalPayload(Request $request, Cotizacion $cotizacion, array $payload): void
    {
        $cliente = Cliente::findOrFail($payload['cliente_id']);
        $delegadoId = $payload['delegado_id'] ?? $cotizacion->delegado_id;
        $delegadoCotizacionId = $payload['delegado_cotizacion_id'] ?? $cotizacion->delegado_cotizacion_id;

        $this->ensureSalesUser($delegadoId ? (int) $delegadoId : null);
        $this->ensureSalesUser($delegadoCotizacionId ? (int) $delegadoCotizacionId : null);

        $cotizacion->update([
            'cliente_id' => $cliente->id,
            'cliente_nombre' => $cliente->nombre,
            'cliente_ruc' => $cliente->ruc,
            'cliente_contacto' => $payload['cliente_contacto'] ?? $cotizacion->cliente_contacto,
            'cliente_telefono' => $cliente->telefono,
            'cliente_correo' => $cliente->correo,
            'plantilla_id' => $payload['plantilla_id'],
            'plataforma_id' => $payload['plataforma_id'],
            'moneda_id' => $payload['moneda_id'],
            'modo_distribucion' => $payload['modo_distribucion'] ?? $cotizacion->modo_distribucion,
            'titulo' => $payload['titulo'],
            'validez_dias' => (int) ($payload['validez_dias'] ?? $cotizacion->validez_dias),
            'subtotal' => 0,
            'igv' => 0,
            'total' => 0,
            'estado_cotizacion_id' => $this->estadoCotizacionId('aprobada'),
            'delegado_id' => $delegadoId,
            'delegado_cotizacion_id' => $delegadoCotizacionId,
            'forma_pago' => $payload['forma_pago'] ?? $cotizacion->forma_pago,
        ]);

        $cotizacion->items()->delete();
        $cotizacion->costosAdicionales()->delete();

        foreach ($payload['items'] as $index => $item) {
            $costoBase = (float) ($item['costo_base'] ?? 0);
            $margen = min((float) ($item['margen'] ?? 0), 99.99);
            $cantidad = (int) ($item['cantidad'] ?? 1);

            $factorMargen = $margen < 100 ? 1 - ($margen / 100) : 0.0001;
            $precioVenta = round($costoBase / $factorMargen, 2);
            $pvt = round($cantidad * $precioVenta, 2);
            $ptc = round($cantidad * $costoBase, 2);

            $itemData = [
                'cotizacion_id' => $cotizacion->id,
                'descripcion' => $item['descripcion'],
                'cantidad' => $cantidad,
                'aplica_costos_adicionales' => $item['aplica_costos_adicionales'] ?? true,
                'costo_base' => $costoBase,
                'margen' => $margen,
                'orden' => $index + 1,
                'marca' => $item['marca'] ?? null,
                'codigo' => $item['codigo'] ?? null,
                'unidad_medida' => $item['unidad_medida'] ?? 'UND',
                'garantia_meses' => $item['garantia_meses'] ?? null,
                'disponibilidad_tipo' => $item['disponibilidad_tipo'] ?? 'stock',
                'disponibilidad_dias' => $item['disponibilidad_dias'] ?? null,
                'proveedor' => $this->primerProveedorNombre($item['proveedores'] ?? null, $item['proveedor'] ?? null),
                'link_proveedor' => $this->primerProveedorLink($item['proveedores'] ?? null, $item['link_proveedor'] ?? null),
                'producto_id' => $item['producto_id'] ?? null,
                'tipo' => $item['tipo'] ?? 'personalizado',
                'imagen' => $this->resolveCotizacionItemImagenFromPayload($item),
                'costo_unitario' => $costoBase,
                'precio_venta' => $precioVenta,
                'subtotal' => $pvt,
                'costo_total' => $ptc,
                'ganancia' => round($pvt - $ptc, 2),
                'stock' => $item['stock'] ?? 0,
            ];

            $itemData['producto_externo_id'] = $this->resolveProductoExternoId($itemData);

            $cotizacionItem = CotizacionItem::create($itemData);
            $this->syncItemProveedores($cotizacionItem, $item['proveedores'] ?? null);
        }

        foreach ($payload['costos'] ?? [] as $costo) {
            CotizacionCostosAdicional::create([
                'cotizacion_id' => $cotizacion->id,
                'tipo' => $costo['tipo'],
                'descripcion' => $costo['descripcion'] ?? null,
                'monto' => $costo['monto'],
            ]);
        }

        $this->service->recalcular($cotizacion);
    }

    /**
     * @return array<string, string>
     */
    private function cotizacionProposalRules(): array
    {
        return [
            'cliente_id' => 'required|exists:clientes,id',
            'plantilla_id' => 'required|exists:plantillas,id',
            'plataforma_id' => 'required|exists:plataformas,id',
            'titulo' => 'required|string',
            'modo_distribucion' => 'nullable|in:POR_ITEM,POR_CANTIDAD',
            'moneda_id' => 'required|exists:monedas,id',
            'delegado_id' => 'nullable|exists:users,id',
            'delegado_cotizacion_id' => 'nullable|exists:users,id',
            'validez_dias' => 'nullable|integer|min:1|max:365',
            'forma_pago' => 'nullable|in:'.implode(',', self::FORMAS_PAGO),
            'cliente_contacto' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string',
            'items.*.cantidad' => 'required|numeric|min:1',
            'items.*.aplica_costos_adicionales' => 'sometimes|boolean',
            'items.*.costo_base' => 'required|numeric|min:0',
            'items.*.margen' => 'required|numeric|min:0',
            'items.*.marca' => 'nullable|string|max:255',
            'items.*.codigo' => 'nullable|string|max:255',
            'items.*.unidad_medida' => 'nullable|string|max:50',
            'items.*.garantia_meses' => 'nullable|integer|in:3,6,12,24,36',
            'items.*.disponibilidad_tipo' => 'nullable|in:stock,importacion',
            'items.*.disponibilidad_dias' => 'nullable|integer|min:1|max:50',
            'items.*.proveedor' => 'nullable|string|max:255',
            'items.*.link_proveedor' => 'nullable|string',
            'items.*.stock' => 'nullable|integer|min:0',
            'items.*.tipo' => 'nullable|string|in:catalogo,personalizado,externo',
            'items.*.producto_id' => 'nullable|exists:productos,id',
            'items.*.producto_externo_id' => 'nullable|exists:productos_externos,id',
            'items.*.imagen' => 'sometimes|nullable',
            'items.*.proveedores' => 'nullable|array',
            'items.*.proveedores.*.nombre' => 'required|string|max:255',
            'items.*.proveedores.*.link' => 'nullable|string',
            'items.*.proveedores.*.precio' => 'nullable|numeric|min:0',
            'items.*.proveedores.*.notas' => 'nullable|string',
            'costos' => 'nullable|array',
            'costos.*.tipo' => 'required|string',
            'costos.*.descripcion' => 'nullable|string',
            'costos.*.monto' => 'required|numeric|min:0',
        ];
    }

    private function ensureSalesUser(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        if (! User::role('ventas')->whereKey($userId)->exists()) {
            abort(422, 'El delegado de cotización debe tener rol de ventas.');
        }
    }

    private function buildCotizacionPdfFilename(Cotizacion $cotizacion): string
    {
        $fecha = Carbon::parse($cotizacion->fecha ?? $cotizacion->created_at)->format('Y-m-d');

        $cliente = $this->sanitizePdfFilenamePart($cotizacion->cliente_nombre ?? 'Cliente');
        $titulo = $this->sanitizePdfFilenamePart($cotizacion->titulo ?? 'Cotizacion');

        return "{$fecha} COT. N-{$cotizacion->numero} - {$cliente} - {$titulo}.pdf";
    }

    private function sanitizePdfFilenamePart(string $value): string
    {
        $value = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function resolveCotizacionPdfView(string $formatoPdf): string
    {
        $fallback = 'pdfs.cotizaciones.willatec-dolares';
        $formatoPdf = trim($formatoPdf);

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $formatoPdf)) {
            return $fallback;
        }

        $vista = 'pdfs.cotizaciones.'.$formatoPdf;
        $basePath = realpath(resource_path('views/pdfs/cotizaciones'));
        $viewPath = realpath(resource_path("views/pdfs/cotizaciones/{$formatoPdf}.blade.php"));

        if (
            ! $basePath ||
            ! $viewPath ||
            ! str_starts_with($viewPath, $basePath.DIRECTORY_SEPARATOR) ||
            ! view()->exists($vista) ||
            trim((string) file_get_contents($viewPath)) === ''
        ) {
            return $fallback;
        }

        return $vista;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $proveedores
     */
    private function syncItemProveedores(CotizacionItem $item, ?array $proveedores): void
    {
        if ($proveedores === null) {
            if ($item->proveedor) {
                $proveedores = [[
                    'nombre' => $item->proveedor,
                    'link' => $item->link_proveedor,
                ]];
            } else {
                return;
            }
        }

        $proveedores = $this->normalizeProveedores($proveedores);

        $item->proveedores()->delete();

        foreach ($proveedores as $index => $proveedor) {
            CotizacionItemProveedor::create([
                'cotizacion_item_id' => $item->id,
                'nombre' => $proveedor['nombre'],
                'link' => $proveedor['link'],
                'precio' => $proveedor['precio'],
                'notas' => $proveedor['notas'],
                'orden' => $index + 1,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $proveedores
     */
    private function primerProveedorNombre(?array $proveedores, ?string $fallback): ?string
    {
        return $this->normalizeProveedores($proveedores)[0]['nombre'] ?? $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $proveedores
     */
    private function primerProveedorLink(?array $proveedores, ?string $fallback): ?string
    {
        return $this->normalizeProveedores($proveedores)[0]['link'] ?? $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $proveedores
     * @return array<int, array{nombre: string, link: ?string, precio: ?float, notas: ?string}>
     */
    private function normalizeProveedores(?array $proveedores): array
    {
        if (! $proveedores) {
            return [];
        }

        return collect($proveedores)
            ->map(function (array $proveedor): ?array {
                $nombre = trim((string) ($proveedor['nombre'] ?? ''));

                if ($nombre === '') {
                    return null;
                }

                return [
                    'nombre' => $nombre,
                    'link' => isset($proveedor['link']) ? trim((string) $proveedor['link']) ?: null : null,
                    'precio' => isset($proveedor['precio']) ? (float) $proveedor['precio'] : null,
                    'notas' => isset($proveedor['notas']) ? trim((string) $proveedor['notas']) ?: null : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveProductoExternoId(array $item): ?int
    {
        if (! empty($item['producto_id'])) {
            return null;
        }

        if (! empty($item['producto_externo_id'])) {
            return (int) $item['producto_externo_id'];
        }

        $fingerprint = ProductoExterno::fingerprintFrom($item);

        return (int) ProductoExterno::firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'descripcion' => $item['descripcion'],
                'marca' => $item['marca'] ?? null,
                'codigo' => $item['codigo'] ?? null,
                'unidad_medida' => $item['unidad_medida'] ?? 'UND',
                'proveedor' => $item['proveedor'] ?? null,
                'link_proveedor' => $item['link_proveedor'] ?? null,
                'costo_base_referencial' => $item['costo_base'] ?? 0,
                'imagen' => $item['imagen'] ?? null,
                'garantia_meses' => $item['garantia_meses'] ?? null,
                'disponibilidad_tipo' => $item['disponibilidad_tipo'] ?? 'stock',
                'disponibilidad_dias' => $item['disponibilidad_dias'] ?? null,
                'stock' => $item['stock'] ?? 0,
                'activo' => true,
            ]
        )->id;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveCotizacionItemImagen(Request $request, int $index, array $item): ?string
    {
        $field = "items.$index.imagen";

        if ($request->hasFile($field)) {
            $request->validate([
                $field => 'image|max:2048',
            ]);

            return $request->file($field)->store('cotizacion-items', 'public');
        }

        foreach (['imagen', 'imagen_path'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return $this->normalizePublicStoragePath($item[$key]);
            }
        }

        if (! empty($item['producto_id'])) {
            return Producto::whereKey($item['producto_id'])->value('imagen');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveCotizacionItemImagenFromPayload(array $item): ?string
    {
        foreach (['imagen', 'imagen_path'] as $key) {
            if (! empty($item[$key]) && is_string($item[$key])) {
                return $this->normalizePublicStoragePath($item[$key]);
            }
        }

        if (! empty($item['producto_id'])) {
            return Producto::whereKey($item['producto_id'])->value('imagen');
        }

        return null;
    }

    private function normalizePublicStoragePath(string $path): ?string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'storage/app/public/')) {
            $path = substr($path, strlen('storage/app/public/'));
        } elseif (str_starts_with($path, 'public/storage/')) {
            $path = substr($path, strlen('public/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            return null;
        }

        if (! preg_match('#^(productos|cotizacion-items)/[A-Za-z0-9._/-]+$#', $path)) {
            return null;
        }

        return $path;
    }

    private function todayBusinessDate(): string
    {
        return Carbon::today(self::BUSINESS_TIMEZONE)->toDateString();
    }

    private function ensureCanDelegateApproval(Request $request): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        abort(403, 'Solo superadmin puede delegar la aprobación.');
    }

    private function estadoCotizacionId(string $nombre): int
    {
        return (int) EstadoCotizacion::where('nombre', $nombre)->firstOrFail()->id;
    }

    private function reenviarSiEstabaRechazada(Request $request, Cotizacion $cotizacion, int $estadoAnteriorId): void
    {
        if ($estadoAnteriorId !== $this->estadoCotizacionId('rechazada')) {
            return;
        }

        $estadoEnviadaId = $this->estadoCotizacionId('enviada');

        $cotizacion->update([
            'estado_cotizacion_id' => $estadoEnviadaId,
        ]);

        if ($estadoAnteriorId === $estadoEnviadaId) {
            return;
        }

        CotizacionHistorial::create([
            'cotizacion_id' => $cotizacion->id,
            'estado_anterior_id' => $estadoAnteriorId,
            'estado_nuevo_id' => $estadoEnviadaId,
            'comentario' => 'Cotización editada luego de rechazo; enviada nuevamente a revisión.',
            'user_id' => $request->user()->id,
        ]);
    }

    private function ensureCanDelegateCotizacion(Request $request, Cotizacion $cotizacion): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ($this->isCotizacionOwnerOrEditDelegate($request, $cotizacion)) {
            return;
        }

        abort(403, 'No autorizado para delegar esta cotización.');
    }

    private function ensureCanEditCotizacion(Request $request, Cotizacion $cotizacion): void
    {
        $this->ensureCotizacionIsNotApprovedForDirectEdit($cotizacion);

        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ($this->isCotizacionOwnerOrEditDelegate($request, $cotizacion)) {
            return;
        }

        abort(403, 'No autorizado para editar esta cotización.');
    }

    private function isCotizacionOwnerOrEditDelegate(Request $request, Cotizacion $cotizacion): bool
    {
        $userId = (int) $request->user()->id;

        return (int) $cotizacion->user_id === $userId
            || ($cotizacion->delegado_cotizacion_id && (int) $cotizacion->delegado_cotizacion_id === $userId);
    }

    private function ensureCanRequestModification(Request $request, Cotizacion $cotizacion): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ($this->isCotizacionOwnerOrEditDelegate($request, $cotizacion)) {
            return;
        }

        abort(403, 'No autorizado para solicitar modificacion de esta cotizacion.');
    }

    private function ensureCanViewModification(Request $request, CotizacionModificacion $modificacion): void
    {
        if ($request->user()->hasAnyRole(['superadmin', 'admin'])) {
            return;
        }

        $cotizacion = $modificacion->cotizacion;
        $userId = (int) $request->user()->id;

        if (
            (int) $modificacion->requested_by === $userId
            || (int) $cotizacion->user_id === $userId
            || ($cotizacion->delegado_cotizacion_id && (int) $cotizacion->delegado_cotizacion_id === $userId)
        ) {
            return;
        }

        abort(403, 'No autorizado para ver esta modificacion.');
    }

    private function ensureCanEditModification(Request $request, CotizacionModificacion $modificacion): void
    {
        if (! in_array($modificacion->estado, [
            CotizacionModificacion::ESTADO_BORRADOR,
            CotizacionModificacion::ESTADO_RECHAZADA,
        ], true)) {
            abort(422, 'La modificacion ya fue enviada a revision y no puede editarse.');
        }

        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ((int) $modificacion->requested_by === (int) $request->user()->id) {
            return;
        }

        abort(403, 'No autorizado para editar esta modificacion.');
    }

    private function ensureCanReviewModification(Request $request): void
    {
        if ($request->user()->hasAnyRole(['superadmin', 'admin'])) {
            return;
        }

        abort(403, 'No autorizado para revisar modificaciones de cotizacion.');
    }

    private function ensureCotizacionIsApproved(Cotizacion $cotizacion): void
    {
        $cotizacion->loadMissing('estadoCotizacion');

        if (strtolower((string) $cotizacion->estadoCotizacion?->nombre) === 'aprobada') {
            return;
        }

        abort(422, 'Solo las cotizaciones aprobadas pueden solicitar modificacion.');
    }

    private function ensureCotizacionIsNotApprovedForDirectEdit(Cotizacion $cotizacion): void
    {
        $cotizacion->loadMissing('estadoCotizacion');

        if (strtolower((string) $cotizacion->estadoCotizacion?->nombre) !== 'aprobada') {
            return;
        }

        abort(422, 'La cotizacion aprobada es de solo lectura. Solicita una modificacion para editarla.');
    }

    private function ensureCotizacionVersionSnapshot(Cotizacion $cotizacion, ?int $userId, ?string $notas = null): CotizacionVersion
    {
        $existing = $cotizacion->versiones()->where('version_number', 1)->first();

        if ($existing) {
            return $existing;
        }

        return CotizacionVersion::create([
            'cotizacion_id' => $cotizacion->id,
            'version_number' => 1,
            'numero_version' => $this->numeroVersion($cotizacion, 1),
            'snapshot' => $this->buildCotizacionSnapshot($cotizacion),
            'created_by' => $cotizacion->user_id,
            'approved_by' => $userId,
            'approved_at' => now(),
            'notas' => $notas,
        ]);
    }

    private function latestCotizacionVersion(Cotizacion $cotizacion): ?CotizacionVersion
    {
        return $cotizacion->versiones()
            ->lockForUpdate()
            ->reorder()
            ->orderByDesc('version_number')
            ->first();
    }

    private function numeroVersion(Cotizacion $cotizacion, int $versionNumber): string
    {
        return "{$cotizacion->numero} V{$versionNumber}";
    }

    // =========================
    // ✅ APROBAR COTIZACIÓN
    // =========================
    private function authorizeApprovalOrRejection(Request $request, Cotizacion $cotizacion): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if (! $request->user()->hasRole('ventas')) {
            abort(403, 'No autorizado.');
        }

        if (! $cotizacion->delegado_id || $cotizacion->delegado_id != $request->user()->id) {
            abort(403, 'No autorizado. Debe ser el delegado asignado para aprobar o rechazar esta cotización.');
        }
    }

    public function aprobar(Request $request, Cotizacion $cotizacion)
    {
        $this->authorizeApprovalOrRejection($request, $cotizacion);

        // Buscar estado "aprobada" (generalmente es id 2 o 3)
        $estadoAprobada = EstadoCotizacion::where('nombre', 'aprobada')
            ->orWhere('nombre', 'Aprobada')
            ->first();

        if (! $estadoAprobada) {
            return response()->json([
                'message' => 'Estado "Aprobada" no encontrado en la base de datos',
            ], 404);
        }

        $estadoAnterior = $cotizacion->estado_cotizacion_id;

        DB::transaction(function () use ($request, $cotizacion, $estadoAprobada, $estadoAnterior) {
            $cotizacion->update([
                'estado_cotizacion_id' => $estadoAprobada->id,
            ]);

            // Registrar en historial
            CotizacionHistorial::create([
                'cotizacion_id' => $cotizacion->id,
                'estado_anterior_id' => $estadoAnterior,
                'estado_nuevo_id' => $estadoAprobada->id,
                'comentario' => null,
                'user_id' => $request->user()->id,
            ]);

            $this->ensureCotizacionVersionSnapshot(
                $cotizacion->refresh(),
                $request->user()->id,
                'Version aprobada inicial.'
            );
        });

        $cotizacion->refresh()->load(['items.proveedores', 'costosAdicionales', 'cliente', 'estadoCotizacion', 'user', 'delegado']);

        if ($cotizacion->user) {
            $cotizacion->user->notify(new CotizacionAprobadaNotification($cotizacion, $request->user()));
        }

        return response()->json([
            'message' => 'Cotización aprobada correctamente',
            'cotizacion' => $cotizacion,
        ], 200);
    }

    // =========================
    // ❌ RECHAZAR COTIZACIÓN
    // =========================
    public function rechazar(Request $request, Cotizacion $cotizacion)
    {
        $this->authorizeApprovalOrRejection($request, $cotizacion);

        $request->validate([
            'comentario_rechazo' => 'required|string|max:500',
        ]);

        // Buscar estado "rechazada"
        $estadoRechazada = EstadoCotizacion::where('nombre', 'rechazada')
            ->orWhere('nombre', 'Rechazada')
            ->first();

        if (! $estadoRechazada) {
            return response()->json([
                'message' => 'Estado "Rechazada" no encontrado en la base de datos',
            ], 404);
        }

        $estadoAnterior = $cotizacion->estado_cotizacion_id;

        DB::transaction(function () use ($request, $cotizacion, $estadoRechazada, $estadoAnterior) {
            $cotizacion->update([
                'estado_cotizacion_id' => $estadoRechazada->id,
            ]);

            // Registrar en historial
            CotizacionHistorial::create([
                'cotizacion_id' => $cotizacion->id,
                'estado_anterior_id' => $estadoAnterior,
                'estado_nuevo_id' => $estadoRechazada->id,
                'comentario' => $request->comentario_rechazo,
                'user_id' => $request->user()->id,
            ]);
        });

        $cotizacion->refresh()->load(['items.proveedores', 'costosAdicionales', 'cliente', 'estadoCotizacion', 'user', 'delegado', 'historial']);

        if ($cotizacion->user) {
            $cotizacion->user->notify(new CotizacionRechazadaNotification($cotizacion, $request->user(), $request->comentario_rechazo));
        }

        return response()->json([
            'message' => 'Cotización rechazada correctamente',
            'cotizacion' => $cotizacion,
        ], 200);
    }

    // SUBIR IMAGEN
    public function uploadImagen(Request $request)
    {
        $request->validate([
            'imagen' => 'required|image|max:2048',
        ]);

        $path = $request->file('imagen')->store('cotizacion-items', 'public');

        return response()->json([
            'path' => $path,
            'url' => asset('storage/'.$path),
        ]);
    }
}
