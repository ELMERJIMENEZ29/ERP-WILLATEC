<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductoRequest;
use App\Http\Requests\UpdateProductoRequest;
use App\Models\Producto;
use App\Models\ProductoSerie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    // Listar Productos
    public function index(Request $request)
    {

        $query = Producto::query()
            ->with([
                'categoria:id,nombre',
                'moneda:id,codigo,simbolo',
                'series' => fn ($query) => $query
                    ->select(['id', 'producto_id', 'serie', 'factura_numero', 'estado', 'fecha_ingreso'])
                    ->latest(),
            ]);

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search): void {
                $query->where('nombre', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('serie', 'like', "%{$search}%")
                    ->orWhere('factura_numero', 'like', "%{$search}%")
                    ->orWhereHas('categoria', function ($categoriaQuery) use ($search): void {
                        $categoriaQuery->where('nombre', 'like', "%{$search}%");
                    })
                    ->orWhereHas('series', function ($seriesQuery) use ($search): void {
                        $seriesQuery->where('serie', 'like', "%{$search}%")
                            ->orWhere('factura_numero', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['marca', 'modelo'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, 'like', '%'.$request->string($field)->toString().'%');
            }
        }

        if ($request->filled('serie')) {
            $serie = $request->string('serie')->toString();

            $query->where(function ($query) use ($serie): void {
                $query->where('serie', 'like', "%{$serie}%")
                    ->orWhereHas('series', function ($seriesQuery) use ($serie): void {
                        $seriesQuery->where('serie', 'like', "%{$serie}%");
                    });
            });
        }

        return response()->json($query->latest()->paginate($request->integer('per_page', 10)));
    }

    // Ver detalle
    public function show(int $id)
    {
        $producto = Producto::with(['categoria:id,nombre', 'moneda:id,codigo,simbolo', 'series'])->findOrFail($id);

        if (! $producto) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        return response()->json($producto);
    }

    // Crear producto
    public function store(StoreProductoRequest $request)
    {
        $stockActual = (float) ($request->input('stock_actual', $request->input('stock', 0)));
        $stockReservado = (float) $request->input('stock_reservado', 0);
        $costoPromedio = (float) $request->input('costo_unitario', 0);
        $series = $this->normalizarSeries($request->input('series', []));
        $seriePrincipal = trim((string) ($request->serie ?: ($series[0] ?? ''))) ?: null;

        $data = [
            'nombre' => $request->nombre,
            'sku' => $request->sku,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'codigo' => $request->codigo ?? $request->sku,
            'codigo_barras' => $request->codigo_barras,
            'serie' => $seriePrincipal,
            'factura_numero' => $request->factura_numero,
            'descripcion' => $request->descripcion,
            'tipo_producto' => $request->tipo_producto ?? 'stock',
            'controla_stock' => $request->boolean('controla_stock', true),
            'stock_actual' => $stockActual,
            'stock_reservado' => $stockReservado,
            'stock_disponible' => max(0, $stockActual - $stockReservado),
            'stock_minimo' => $request->input('stock_minimo', 0),
            'costo_unitario' => $request->input('costo_unitario', 0),
            'costo_promedio' => $costoPromedio,
            'valor_stock' => round($stockActual * $costoPromedio, 2),
            'precio_venta' => $request->input('precio_venta', $request->input('precio_referencial', 0)),
            'moneda_id' => $request->moneda_id,
            'precio_referencial' => $request->precio_referencial,
            'unidad_medida' => $request->unidad_medida,
            'estado' => $request->estado ?? 'nuevo',
            'stock' => (int) round($stockActual),
            'categoria_id' => $request->categoria_id,
            'activo' => $request->boolean('activo', true),
        ];

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto = Producto::create($data);
        $this->syncSeriesProducto($producto, $series);
        $producto->load(['categoria:id,nombre', 'moneda:id,codigo,simbolo', 'series']);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto,
        ], 201);
    }

    // Actualizar producto
    public function update(UpdateProductoRequest $request, int $id)
    {

        $producto = Producto::findOrFail($id);
        $series = $this->normalizarSeries($request->input('series', []));

        $data = $request->only([
            'nombre',
            'sku',
            'marca',
            'modelo',
            'codigo',
            'codigo_barras',
            'serie',
            'factura_numero',
            'descripcion',
            'tipo_producto',
            'controla_stock',
            'stock_actual',
            'stock_reservado',
            'stock_minimo',
            'costo_unitario',
            'costo_promedio',
            'valor_stock',
            'precio_venta',
            'moneda_id',
            'precio_referencial',
            'unidad_medida',
            'estado',
            'stock',
            'categoria_id',
        ]);

        if (! empty($series) && empty($data['serie'])) {
            $data['serie'] = $series[0];
        }

        if ($request->has('activo')) {
            $data['activo'] = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->filled('stock_actual') || $request->filled('stock_reservado') || $request->filled('stock')) {
            $stockActual = (float) ($data['stock_actual'] ?? $request->input('stock', $producto->stock_actual ?? 0));
            $stockReservado = (float) ($data['stock_reservado'] ?? $producto->stock_reservado ?? 0);

            $data['stock_actual'] = $stockActual;
            $data['stock_reservado'] = $stockReservado;
            $data['stock_disponible'] = max(0, $stockActual - $stockReservado);
            $data['stock'] = (int) round($stockActual);
        }

        if ($request->filled('costo_unitario')) {
            $data['costo_promedio'] = (float) $request->input('costo_unitario');
        }

        if (array_key_exists('costo_promedio', $data) || array_key_exists('stock_actual', $data)) {
            $stockActual = (float) ($data['stock_actual'] ?? $producto->stock_actual ?? 0);
            $costoPromedio = (float) ($data['costo_promedio'] ?? $producto->costo_promedio ?? $producto->costo_unitario ?? 0);
            $data['valor_stock'] = round($stockActual * $costoPromedio, 2);
        }

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }

            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        }

        $producto->update($data);
        $this->syncSeriesProducto($producto, $series);
        $producto->load(['categoria:id,nombre', 'moneda:id,codigo,simbolo', 'series']);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto,
        ]);
    }

    // Eliminar Producto
    public function destroy(int $id)
    {
        $producto = Producto::findOrFail($id);

        if (! $producto) {
            return response()->json([
                'message' => 'Producto no encontrado',
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente',
        ]);
    }

    /**
     * @param  array<int, string|null>  $series
     * @return array<int, string>
     */
    private function normalizarSeries(array $series): array
    {
        return collect($series)
            ->map(fn ($serie) => trim((string) $serie))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $series
     */
    private function syncSeriesProducto(Producto $producto, array $series): void
    {
        $serie = trim((string) $producto->serie);

        if ($serie !== '') {
            array_unshift($series, $serie);
        }

        foreach (array_values(array_unique(array_filter($series))) as $serieItem) {
            ProductoSerie::query()->updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'serie' => $serieItem,
                ],
                [
                    'factura_numero' => $producto->factura_numero,
                    'costo_unitario' => $producto->costo_unitario,
                    'moneda_id' => $producto->moneda_id,
                    'estado' => ProductoSerie::ESTADO_DISPONIBLE,
                    'created_by' => auth()->id(),
                ]
            );
        }
    }
}
