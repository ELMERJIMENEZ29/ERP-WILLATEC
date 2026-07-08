<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CotizacionItem;
use App\Models\ProductoExterno;
use Illuminate\Http\Request;

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
}
