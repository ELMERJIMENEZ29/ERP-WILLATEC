<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AjustarStockRequest;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

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
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = InventarioMovimiento::query()
            ->with([
                'producto:id,nombre,sku,codigo',
                'moneda:id,codigo,simbolo,nombre',
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
                    ->orWhereHas('producto', function ($query) use ($search): void {
                        $query->where('nombre', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 15))
        );
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
            'proveedor' => 'nullable|string|max:255',
            'documento_tipo' => 'nullable|string|max:40',
            'documento_numero' => 'nullable|string|max:100',
            'fecha_documento' => 'nullable|date',
            'observacion' => 'nullable|string',
            'factura' => 'nullable|file|mimes:pdf,jpg,jpeg,png,xml,doc,docx|max:10240',
        ]);

        $documentoPath = $request->hasFile('factura')
            ? $this->storeDocumento($request->file('factura'), 'inventario/facturas')
            : null;

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
            proveedor: $validated['proveedor'] ?? null
        );

        return response()->json([
            'message' => 'Entrada registrada en Kardex',
            'producto' => $productoActualizado,
        ]);
    }

    private function storeDocumento(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }
}
