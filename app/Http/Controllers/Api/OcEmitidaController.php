<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\OcDocumentoAdicional;
use App\Models\OcEmitida;
use App\Models\OcEmitidaItem;
use App\Models\Proveedor;
use App\Models\User;
use App\Notifications\OcEmitidaRegistradaNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OcEmitidaController extends Controller
{
    public function preview(Cotizacion $cotizacion)
    {
        $cotizacion->load(['items.proveedores']);

        return response()->json([
            'cotizacion' => [
                'id' => $cotizacion->id,
                'numero' => $cotizacion->numero,
                'cliente_nombre' => $cotizacion->cliente_nombre,
            ],
            'proveedores' => $this->proveedoresDeCotizacion($cotizacion)
                ->map(fn (array $proveedor): array => [
                    'id' => $proveedor['proveedor_id'],
                    'nombre' => $proveedor['nombre'],
                    'items_count' => $this->itemsPorProveedor($cotizacion, $proveedor)->count(),
                ])
                ->values(),
        ]);
    }

    public function itemsPorProveedorResponse(Request $request, Cotizacion $cotizacion)
    {
        $validated = $request->validate([
            'proveedor' => 'required|string|max:255',
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
        ]);

        $cotizacion->load(['items.proveedores']);
        $proveedor = $this->resolveProveedorSeleccionado($validated);
        $items = $this->buildItemsProveedor($cotizacion, $proveedor);

        return response()->json([
            'proveedor' => $proveedor['nombre'],
            'proveedor_id' => $proveedor['proveedor_id'],
            'items' => $items,
            'totales' => $this->calcularTotales($items),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'proveedor' => 'nullable|string|max:255',
            'estado' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = OcEmitida::query()
            ->with(['cotizacion:id,numero,titulo', 'cliente:id,nombre,ruc', 'documentosAdicionales'])
            ->withCount('items');

        if ($request->filled('proveedor')) {
            $query->where('proveedor', $request->proveedor);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search): void {
                $query->where('numero', 'like', "%{$search}%")
                    ->orWhere('proveedor', 'like', "%{$search}%")
                    ->orWhere('cliente_nombre', 'like', "%{$search}%")
                    ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery->where('numero', 'like', "%{$search}%"));
            });
        }

        return response()->json(
            $query->latest()->paginate($request->integer('per_page', 10))
        );
    }

    public function show(OcEmitida $ocEmitida)
    {
        return response()->json(
            $ocEmitida->load(['items.cotizacionItem.proveedores', 'cotizacion', 'cliente', 'documentosAdicionales'])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cotizacion_id' => 'required|exists:cotizaciones,id',
            'proveedor' => 'required|string|max:255',
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
            'fecha_emision' => 'nullable|date',
            'observaciones' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.cotizacion_item_id' => 'required|integer|exists:cotizacion_items,id',
            'items.*.cantidad' => 'required|integer|min:1',
            'items.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        $cotizacion = Cotizacion::with(['items.proveedores', 'cliente', 'moneda'])->findOrFail($validated['cotizacion_id']);
        $this->ensureCanCreateOcForCotizacion($request, $cotizacion);
        $proveedorSeleccionado = $this->resolveProveedorSeleccionado($validated);
        $proveedores = $this->proveedoresDeCotizacion($cotizacion);

        if (! $proveedores->contains(fn (array $proveedor): bool => $this->sameProveedor($proveedor, $proveedorSeleccionado))) {
            return response()->json([
                'message' => 'El proveedor no esta asociado a la cotizacion seleccionada.',
            ], 422);
        }

        $idsProveedor = $this->itemsPorProveedor($cotizacion, $proveedorSeleccionado)->pluck('id');
        $idsSolicitados = collect($validated['items'])->pluck('cotizacion_item_id');

        if ($idsSolicitados->diff($idsProveedor)->isNotEmpty()) {
            return response()->json([
                'message' => 'Todos los items deben pertenecer al proveedor seleccionado.',
            ], 422);
        }

        $ocEmitida = DB::transaction(function () use ($request, $validated, $cotizacion, $proveedorSeleccionado): OcEmitida {
            $itemsById = $cotizacion->items->keyBy('id');
            $subtotal = collect($validated['items'])->sum(
                fn (array $item): float => round((int) $item['cantidad'] * (float) $item['precio_unitario'], 2)
            );
            $igv = round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);

            $ocEmitida = OcEmitida::create([
                'numero' => $this->generarNumero(),
                'fecha_emision' => $validated['fecha_emision'] ?? now()->toDateString(),
                'estado' => OcEmitida::ESTADO_EMITIDA,
                'proveedor' => $proveedorSeleccionado['nombre'],
                'observaciones' => $validated['observaciones'] ?? null,
                'moneda' => $cotizacion->moneda?->codigo ?? 'PEN',
                'subtotal' => round($subtotal, 2),
                'igv' => $igv,
                'total' => $total,
                'cliente_nombre' => $cotizacion->cliente_nombre,
                'cliente_ruc' => $cotizacion->cliente_ruc,
                'cliente_contacto' => $cotizacion->cliente_contacto,
                'cliente_correo' => $cotizacion->cliente_correo,
                'cotizacion_id' => $cotizacion->id,
                'cliente_id' => $cotizacion->cliente_id,
                'user_id' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $itemData) {
                $cotizacionItem = $itemsById->get((int) $itemData['cotizacion_item_id']);
                $itemSubtotal = round((int) $itemData['cantidad'] * (float) $itemData['precio_unitario'], 2);

                OcEmitidaItem::create([
                    'oc_emitida_id' => $ocEmitida->id,
                    'cotizacion_item_id' => $cotizacionItem->id,
                    'descripcion' => $cotizacionItem->descripcion,
                    'codigo' => $cotizacionItem->codigo,
                    'marca' => $cotizacionItem->marca,
                    'unidad_medida' => $cotizacionItem->unidad_medida,
                    'cantidad' => (int) $itemData['cantidad'],
                    'precio_unitario' => round((float) $itemData['precio_unitario'], 2),
                    'subtotal' => $itemSubtotal,
                ]);
            }

            $ocEmitida->load(['items', 'cotizacion', 'cliente']);
            $ocEmitida->update(['pdf_path' => $this->generarPdf($ocEmitida)]);

            return $ocEmitida->refresh()->load(['items', 'cotizacion']);
        });

        $this->notifyAdministrators(new OcEmitidaRegistradaNotification($ocEmitida, $request->user()));

        return response()->json([
            'message' => 'OC EMITIDA',
            'oc_emitida' => $ocEmitida,
            'pdf_url' => url("/api/oc-emitidas/{$ocEmitida->id}/pdf"),
        ], 201);
    }

    public function documentos(Request $request, OcEmitida $ocEmitida)
    {
        $this->ensureCanUploadDocuments($request, $ocEmitida);

        $request->validate([
            'factura' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'comprobante_pago' => 'nullable|file|mimes:pdf,xml,doc,docx|max:10240',
            'documentos_adicionales' => 'nullable|array',
            'documentos_adicionales.*' => 'file|mimes:pdf,xml,doc,docx,jpg,jpeg,png,xls,xlsx|max:10240',
        ]);

        if ($request->hasFile('factura')) {
            $ocEmitida->factura_path = $this->storeDocumento($request->file('factura'), 'oc-emitidas/documentos');
            $ocEmitida->factura_uploaded_by = $request->user()?->id;
        }

        if ($request->hasFile('comprobante_pago')) {
            $ocEmitida->comprobante_pago_path = $this->storeDocumento($request->file('comprobante_pago'), 'oc-emitidas/documentos');
            $ocEmitida->comprobante_pago_uploaded_by = $request->user()?->id;
        }

        $ocEmitida->save();
        $this->storeDocumentosAdicionales($request, $ocEmitida);

        return response()->json([
            'message' => 'Documentos actualizados',
            'documentos_completos' => $ocEmitida->documentos_completos,
            'faltantes' => $ocEmitida->documentos_faltantes,
            'oc_emitida' => $ocEmitida->refresh()->load('documentosAdicionales'),
        ]);
    }

    public function eliminarDocumento(Request $request, OcEmitida $ocEmitida, string $tipo)
    {
        $column = match ($tipo) {
            'factura' => 'factura_path',
            'comprobante_pago' => 'comprobante_pago_path',
            default => null,
        };
        $uploaderColumn = match ($tipo) {
            'factura' => 'factura_uploaded_by',
            'comprobante_pago' => 'comprobante_pago_uploaded_by',
            default => null,
        };

        if (! $column || ! $uploaderColumn) {
            return response()->json(['message' => 'Tipo de documento no valido.'], 422);
        }

        $this->ensureCanDeleteDocumento($request, $ocEmitida, $ocEmitida->{$uploaderColumn});

        if ($ocEmitida->{$column}) {
            Storage::disk('public')->delete($ocEmitida->{$column});
        }

        $ocEmitida->forceFill([$column => null, $uploaderColumn => null])->save();

        return response()->json([
            'message' => 'Documento eliminado',
            'oc_emitida' => $ocEmitida->refresh()->load('documentosAdicionales'),
        ]);
    }

    public function eliminarDocumentoAdicional(Request $request, OcEmitida $ocEmitida, OcDocumentoAdicional $documento)
    {
        $this->ensureCanDeleteDocumento($request, $ocEmitida, $documento->created_by);

        if ((int) $documento->oc_emitida_id !== (int) $ocEmitida->id) {
            abort(404);
        }

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return response()->json([
            'message' => 'Documento adicional eliminado',
            'oc_emitida' => $ocEmitida->refresh()->load('documentosAdicionales'),
        ]);
    }

    public function pdf(OcEmitida $ocEmitida)
    {
        if (! $ocEmitida->pdf_path || ! Storage::disk('public')->exists($ocEmitida->pdf_path)) {
            $ocEmitida->load(['items', 'cotizacion', 'cliente']);
            $ocEmitida->update(['pdf_path' => $this->generarPdf($ocEmitida)]);
            $ocEmitida->refresh();
        }

        return Storage::disk('public')->download($ocEmitida->pdf_path, "{$ocEmitida->numero}.pdf");
    }

    /**
     * @return Collection<int, array{proveedor_id: ?int, nombre: string, key: string}>
     */
    private function proveedoresDeCotizacion(Cotizacion $cotizacion): Collection
    {
        $proveedores = $cotizacion->items
            ->flatMap(function ($item): array {
                $proveedores = $item->proveedores->map(fn ($proveedor): array => $this->proveedorDescriptor(
                    $proveedor->nombre,
                    $proveedor->proveedor_id,
                ))->all();

                if ($proveedores === [] && filled($item->proveedor)) {
                    $proveedores[] = $this->proveedorDescriptor($item->proveedor);
                }

                return $proveedores;
            })
            ->filter(fn (array $proveedor): bool => $proveedor['key'] !== '')
            ->values();

        return $proveedores
            ->unique(fn (array $proveedor): string => $proveedor['key'])
            ->values();
    }

    /**
     * @return Collection<int, mixed>
     */
    private function itemsPorProveedor(Cotizacion $cotizacion, array $proveedor): Collection
    {
        return $cotizacion->items
            ->filter(function ($item) use ($proveedor): bool {
                if ($item->proveedores->isNotEmpty()) {
                    return $item->proveedores->contains(
                        fn ($itemProveedor): bool => $this->sameProveedor(
                            $this->proveedorDescriptor($itemProveedor->nombre, $itemProveedor->proveedor_id),
                            $proveedor,
                        )
                    );
                }

                return $this->sameProveedor($this->proveedorDescriptor($item->proveedor), $proveedor);
            })
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildItemsProveedor(Cotizacion $cotizacion, array $proveedor): array
    {
        return $this->itemsPorProveedor($cotizacion, $proveedor)
            ->map(function ($item) use ($proveedor): array {
                $proveedorData = $item->proveedores->first(
                    fn ($itemProveedor): bool => $this->sameProveedor(
                        $this->proveedorDescriptor($itemProveedor->nombre, $itemProveedor->proveedor_id),
                        $proveedor,
                    )
                );
                $precio = $proveedorData?->precio ?? $item->costo_unitario ?? 0;
                $subtotal = round((int) $item->cantidad * (float) $precio, 2);

                return [
                    'cotizacion_item_id' => $item->id,
                    'descripcion' => $item->descripcion,
                    'codigo' => $item->codigo,
                    'marca' => $item->marca,
                    'unidad_medida' => $item->unidad_medida,
                    'cantidad' => $item->cantidad,
                    'precio_unitario' => round((float) $precio, 2),
                    'subtotal' => $subtotal,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{proveedor_id: ?int, nombre: string, key: string}
     */
    private function resolveProveedorSeleccionado(array $validated): array
    {
        $proveedor = ! empty($validated['proveedor_id'])
            ? Proveedor::query()->find($validated['proveedor_id'])
            : Proveedor::query()
                ->where('activo', true)
                ->get(['id', 'nombre'])
                ->first(fn (Proveedor $row): bool => $this->normalizeProveedorKey($row->nombre) === $this->normalizeProveedorKey($validated['proveedor']));

        return $this->proveedorDescriptor(
            $proveedor?->nombre ?: $validated['proveedor'],
            $proveedor?->id,
        );
    }

    /**
     * @return array{proveedor_id: ?int, nombre: string, key: string}
     */
    private function proveedorDescriptor(?string $nombre, ?int $proveedorId = null): array
    {
        $nombre = trim((string) $nombre);

        return [
            'proveedor_id' => $proveedorId,
            'nombre' => $nombre,
            'key' => $this->normalizeProveedorKey($nombre),
        ];
    }

    /**
     * @param  array{proveedor_id: ?int, nombre: string, key: string}  $left
     * @param  array{proveedor_id: ?int, nombre: string, key: string}  $right
     */
    private function sameProveedor(array $left, array $right): bool
    {
        if ($left['proveedor_id'] && $right['proveedor_id']) {
            return (int) $left['proveedor_id'] === (int) $right['proveedor_id'];
        }

        return $left['key'] !== '' && $left['key'] === $right['key'];
    }

    private function normalizeProveedorKey(?string $value): string
    {
        $normalized = trim((string) $value);
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/i', '', $normalized) ?? '';

        return strtolower($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, float>
     */
    private function calcularTotales(array $items): array
    {
        $subtotal = round(collect($items)->sum('subtotal'), 2);
        $igv = round($subtotal * 0.18, 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'total' => round($subtotal + $igv, 2),
        ];
    }

    private function generarNumero(): string
    {
        return 'OCE-'.str_pad((string) (OcEmitida::count() + 1), 6, '0', STR_PAD_LEFT);
    }

    private function ensureCanCreateOcForCotizacion(Request $request, Cotizacion $cotizacion): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ((int) $cotizacion->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'Solo el creador de la cotizacion puede asociar la orden de compra.');
    }

    private function ensureCanEditOc(Request $request, OcEmitida $ocEmitida): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if ((int) $ocEmitida->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'Solo el usuario que registro esta orden de compra puede editarla.');
    }

    private function ensureCanUploadDocuments(Request $request, OcEmitida $ocEmitida): void
    {
        if ($request->user()->hasAnyRole(['superadmin', 'admin', 'contabilidad'])) {
            return;
        }

        if ((int) $ocEmitida->user_id === (int) $request->user()->id) {
            return;
        }

        abort(403, 'No tienes permisos para subir documentos a esta orden de compra.');
    }

    private function ensureCanDeleteDocumento(Request $request, OcEmitida $ocEmitida, mixed $uploadedBy): void
    {
        if ($request->user()->hasRole('superadmin')) {
            return;
        }

        if (
            $request->user()->hasRole('ventas') &&
            (int) $ocEmitida->user_id === (int) $request->user()->id
        ) {
            return;
        }

        if (
            $request->user()->hasAnyRole(['admin', 'contabilidad']) &&
            $uploadedBy &&
            (int) $uploadedBy === (int) $request->user()->id
        ) {
            return;
        }

        abort(403, 'Solo puedes eliminar documentos que hayas subido.');
    }

    private function generarPdf(OcEmitida $ocEmitida): string
    {
        $path = "oc-emitidas/pdfs/{$ocEmitida->numero}.pdf";
        $pdf = Pdf::loadView('pdfs.oc-emitidas.simple', ['ocEmitida' => $ocEmitida])
            ->setPaper('A4', 'portrait')
            ->setOptions([
                'isRemoteEnabled' => false,
                'isLocalEnabled' => true,
                'chroot' => public_path(),
            ]);

        Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }

    private function storeDocumento(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function storeDocumentosAdicionales(Request $request, OcEmitida $ocEmitida): void
    {
        if (! $request->hasFile('documentos_adicionales')) {
            return;
        }

        foreach ($request->file('documentos_adicionales', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ocEmitida->documentosAdicionales()->create([
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $this->storeDocumento($file, 'oc-emitidas/adicionales'),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'created_by' => $request->user()?->id,
            ]);
        }
    }

    private function notifyAdministrators(object $notification): void
    {
        User::role(['superadmin', 'admin', 'contabilidad'])->get()->each->notify($notification);
    }
}
