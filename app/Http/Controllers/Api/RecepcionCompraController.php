<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmarRecepcionCompraRequest;
use App\Http\Requests\StoreRecepcionCompraRequest;
use App\Models\Compra;
use App\Models\RecepcionCompra;
use App\Services\RecepcionCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecepcionCompraController extends Controller
{
    public function __construct(private readonly RecepcionCompraService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:30'],
            'compra_id' => ['nullable', 'integer', 'exists:compras,id'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = RecepcionCompra::query()
            ->with(['compra:id,numero,estado,total_estimado', 'proveedor:id,nombre,ruc', 'recibidoPor:id,nombres,apellidos,email'])
            ->withCount('items')
            ->latest('id');

        if ($request->filled('estado') && $request->estado !== 'todos') {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('compra_id')) {
            $query->where('compra_id', $request->integer('compra_id'));
        }

        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->integer('proveedor_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($query) use ($search): void {
                $query->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('compra', fn ($compraQuery) => $compraQuery->where('numero', 'like', "%{$search}%"))
                    ->orWhereHas('proveedor', fn ($proveedorQuery) => $proveedorQuery->where('nombre', 'like', "%{$search}%"));
            });
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function store(StoreRecepcionCompraRequest $request, Compra $compra): JsonResponse
    {
        return response()->json(
            $this->service->crear($compra, $request->validated(), $request),
            201
        );
    }

    public function show(RecepcionCompra $recepcion): JsonResponse
    {
        return response()->json(
            $recepcion->load([
                'compra.proveedor',
                'proveedor',
                'recibidoPor:id,nombres,apellidos,email',
                'items.compraItem.requerimientoCompraItem.requerimiento',
                'items.producto',
                'items.moneda',
                'items.inventarioMovimiento',
                'items.series',
            ])
        );
    }

    public function confirmar(ConfirmarRecepcionCompraRequest $request, RecepcionCompra $recepcion): JsonResponse
    {
        return response()->json(
            $this->service->confirmar($recepcion, $request->validated(), $request)
        );
    }

    public function cancelar(Request $request, RecepcionCompra $recepcion): JsonResponse
    {
        return response()->json(
            $this->service->cancelar($recepcion)
        );
    }
}
