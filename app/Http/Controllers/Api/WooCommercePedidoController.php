<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WooCommercePedido;
use App\Services\WooCommerce\WooCommercePedidoService;
use Illuminate\Http\Request;

class WooCommercePedidoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'estado_erp' => ['nullable', 'string', 'max:40'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WooCommercePedido::query()
            ->with(['items.producto:id,nombre,sku,codigo,stock_actual,stock_reservado,stock_disponible'])
            ->withCount('items')
            ->latest('fecha_woo');

        if ($request->filled('estado_erp')) {
            $query->where('estado_erp', $request->string('estado_erp')->toString());
        }

        if ($request->filled('search')) {
            $search = mb_strtolower($request->string('search')->toString(), 'UTF-8');
            $like = "%{$search}%";

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('LOWER(numero) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cliente_nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(cliente_email) LIKE ?', [$like])
                    ->orWhereHas('items', function ($itemsQuery) use ($like): void {
                        $itemsQuery->whereRaw('LOWER(sku) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(nombre) LIKE ?', [$like]);
                    });
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 10)));
    }

    public function show(WooCommercePedido $pedido)
    {
        return response()->json(
            $pedido->load(['items.producto:id,nombre,sku,codigo,stock_actual,stock_reservado,stock_disponible'])
        );
    }

    public function sincronizar(Request $request, WooCommercePedidoService $service)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
        ]);

        $result = $service->sincronizar(
            perPage: $request->integer('per_page', 25),
            status: $request->input('status')
        );

        return response()->json([
            'message' => "Pedidos WooCommerce sincronizados: {$result['total']}.",
            'resumen' => $result,
        ]);
    }

    public function reservar(Request $request, WooCommercePedido $pedido, WooCommercePedidoService $service)
    {
        $pedido = $service->reservarPedido($pedido, $request);

        return response()->json([
            'message' => 'Stock reservado para el pedido WooCommerce.',
            'pedido' => $pedido,
        ]);
    }
}
