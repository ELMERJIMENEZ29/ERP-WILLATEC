<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CotizacionItem;
use App\Models\InventarioMovimiento;
use App\Models\OcRecibida;
use App\Models\OcRecibidaItem;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoExternoController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'activo' => 'nullable|in:true,false,0,1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ProductoExterno::query()
            ->with([
                'producto:id,nombre,sku,codigo,stock_actual,stock_reservado,stock_disponible',
                'moneda',
                'plantillaOrigen',
                'ultimoCotizacionItem.proveedores',
                'ultimoCotizacionItem.cotizacion.plantilla',
                'ultimoCotizacionItemConProveedores.proveedores',
                'ultimoCotizacionItemConProveedores.cotizacion.plantilla',
            ])
            ->withCount(['cotizacionItems as veces_cotizado'])
            ->addSelect([
                'ultimo_margen_usado' => CotizacionItem::select('margen')
                    ->whereColumn('cotizacion_items.producto_externo_id', 'productos_externos.id')
                    ->latest('cotizacion_items.created_at')
                    ->limit(1),
                'ultimo_precio_venta' => CotizacionItem::select('precio_venta')
                    ->whereColumn('cotizacion_items.producto_externo_id', 'productos_externos.id')
                    ->latest('cotizacion_items.created_at')
                    ->limit(1),
                'ultima_fecha_cotizacion' => CotizacionItem::select('cotizaciones.fecha')
                    ->join('cotizaciones', 'cotizaciones.id', '=', 'cotizacion_items.cotizacion_id')
                    ->whereColumn('cotizacion_items.producto_externo_id', 'productos_externos.id')
                    ->latest('cotizacion_items.created_at')
                    ->limit(1),
            ]);

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search): void {
                $query->where('descripcion', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('proveedor', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query
                ->latest()
                ->paginate($request->integer('per_page', 10))
        );
    }

    public function convertirAInterno(Request $request, ProductoExterno $productoExterno, InventarioService $inventarioService)
    {
        $validated = $request->validate([
            'cantidad' => ['required', 'numeric', 'min:0.01'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', 'exists:monedas,id'],
            'documento_numero' => ['required', 'string', 'max:100'],
            'factura' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,xml,doc,docx', 'max:10240'],
            'categoria_id' => ['nullable', 'exists:categorias,id'],
            'estado' => ['nullable', Rule::in(['nuevo', 'usado'])],
            'observacion' => ['nullable', 'string'],
        ]);

        $monedaId = $validated['moneda_id'] ?? $productoExterno->moneda_id;
        $facturaPath = $this->storeDocumento($request->file('factura'), 'inventario/facturas');

        $producto = DB::transaction(function () use ($request, $validated, $productoExterno, $inventarioService, $facturaPath, $monedaId): Producto {
            $codigoInterno = $productoExterno->producto_id ? null : $this->buildNextInternalCode();
            $producto = $productoExterno->producto_id
                ? Producto::query()->lockForUpdate()->findOrFail($productoExterno->producto_id)
                : Producto::create([
                    'nombre' => $productoExterno->descripcion,
                    'sku' => $codigoInterno,
                    'marca' => $productoExterno->marca,
                    'modelo' => null,
                    'codigo' => $codigoInterno,
                    'descripcion' => $productoExterno->descripcion,
                    'tipo_producto' => 'stock',
                    'controla_stock' => true,
                    'stock_actual' => 0,
                    'stock_reservado' => 0,
                    'stock_disponible' => 0,
                    'stock_minimo' => 0,
                    'costo_unitario' => 0,
                    'costo_promedio' => 0,
                    'valor_stock' => 0,
                    'precio_venta' => $productoExterno->costo_base_referencial ?? 0,
                    'precio_referencial' => $productoExterno->costo_base_referencial ?? 0,
                    'moneda_id' => $monedaId,
                    'unidad_medida' => $productoExterno->unidad_medida ?? 'UND',
                    'imagen' => $productoExterno->imagen,
                    'activo' => true,
                    'estado' => $validated['estado'] ?? 'nuevo',
                    'stock' => 0,
                    'categoria_id' => $validated['categoria_id'] ?? 1,
                ]);

            $productoExterno->forceFill(['producto_id' => $producto->id])->save();

            CotizacionItem::query()
                ->where('producto_externo_id', $productoExterno->id)
                ->whereNull('producto_id')
                ->update(['producto_id' => $producto->id]);

            $inventarioService->registrarEntrada(
                productoId: $producto->id,
                cantidad: (float) $validated['cantidad'],
                referenciaTipo: 'producto_externo',
                referenciaId: $productoExterno->id,
                origen: 'producto_externo',
                idempotencyKey: null,
                createdBy: $request->user()?->id,
                observacion: $validated['observacion'] ?? "Entrada por conversion de producto externo #{$productoExterno->id}",
                ipOrigen: $request->ip(),
                userAgent: $request->userAgent(),
                costoUnitario: (float) $validated['costo_unitario'],
                monedaId: $monedaId ? (int) $monedaId : null,
                documentoTipo: 'factura',
                documentoNumero: $validated['documento_numero'],
                documentoPath: $facturaPath,
                fechaDocumento: now()->toDateString(),
                proveedor: $productoExterno->proveedor
            );

            $this->reservarOcRecibidasPendientes(
                productoExterno: $productoExterno,
                producto: $producto,
                request: $request,
                inventarioService: $inventarioService
            );

            return $producto->refresh();
        });

        return response()->json([
            'message' => 'Producto externo convertido y entrada Kardex registrada',
            'producto' => $producto,
            'producto_externo' => $productoExterno->refresh()->load('producto'),
        ]);
    }

    private function reservarOcRecibidasPendientes(
        ProductoExterno $productoExterno,
        Producto $producto,
        Request $request,
        InventarioService $inventarioService
    ): void {
        $items = OcRecibidaItem::query()
            ->with('ocRecibida.cotizacion')
            ->where('seleccionado', true)
            ->where('entregado', false)
            ->whereHas('cotizacionItem', function ($query) use ($productoExterno): void {
                $query->where('producto_externo_id', $productoExterno->id);
            })
            ->whereHas('ocRecibida', function ($query): void {
                $query->whereIn('estado', [
                    OcRecibida::ESTADO_PENDIENTE,
                    OcRecibida::ESTADO_EN_PROCESO,
                    OcRecibida::ESTADO_POR_ENTREGA,
                ]);
            })
            ->get();

        foreach ($items as $item) {
            $ocRecibida = $item->ocRecibida;

            if (! $ocRecibida) {
                continue;
            }

            $idempotencyKey = "oc-recibida:{$ocRecibida->id}:reserva:cotizacion-item:{$item->cotizacion_item_id}";

            if (InventarioMovimiento::where('idempotency_key', $idempotencyKey)->exists()) {
                continue;
            }

            $inventarioService->reservarStock(
                productoId: $producto->id,
                cantidad: (float) $item->cantidad_recibida,
                referenciaTipo: 'oc_recibida',
                referenciaId: $ocRecibida->id,
                origen: 'orden_compra',
                idempotencyKey: $idempotencyKey,
                createdBy: $request->user()?->id,
                observacion: "Reserva retroactiva por conversion de producto externo para OC recibida {$ocRecibida->numero}",
                ipOrigen: $request->ip(),
                userAgent: $request->userAgent(),
                monedaId: $ocRecibida->cotizacion?->moneda_id
            );
        }
    }

    private function buildNextInternalCode(): string
    {
        $max = Producto::query()
            ->pluck('codigo')
            ->filter(fn ($codigo): bool => is_scalar($codigo) && ctype_digit((string) $codigo))
            ->map(fn ($codigo): int => (int) $codigo)
            ->max() ?? 0;

        do {
            $max++;
            $candidate = str_pad((string) $max, 4, '0', STR_PAD_LEFT);
        } while (
            Producto::where('codigo', $candidate)->exists()
            || Producto::where('sku', $candidate)->exists()
        );

        return $candidate;
    }

    private function storeDocumento(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }
}
