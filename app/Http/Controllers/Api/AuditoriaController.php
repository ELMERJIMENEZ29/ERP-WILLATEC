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
use App\Models\InventarioMovimiento;
use App\Models\OcDocumentoAdicional;
use App\Models\OcEmitida;
use App\Models\OcEmitidaItem;
use App\Models\OcRecibida;
use App\Models\OcRecibidaItem;
use App\Models\Producto;
use App\Models\ProductoExterno;
use App\Models\ProductoSerie;
use App\Models\Proveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    /**
     * List the system audit trail.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'event' => 'nullable|in:created,updated,deleted,uploaded',
            'tipo' => 'nullable|in:cliente,cotizacion,cotizacion_item,cotizacion_costo,cotizacion_item_proveedor,cotizacion_historial,cotizacion_modificacion,cotizacion_version,producto,producto_externo,producto_serie,inventario_movimiento,proveedor,oc_recibida,oc_recibida_item,oc_emitida,oc_emitida_item,oc_documento_adicional',
            'subject_id' => 'nullable|integer|min:1',
            'causer_id' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = Activity::query()
            ->where('log_name', 'auditoria')
            ->with([
                'causer:id,nombres,apellidos,email',
                'subject',
            ])
            ->latest();

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('tipo')) {
            $query->where('subject_type', $this->subjectTypes()[$request->tipo]);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->integer('causer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($query) use ($search): void {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereRaw($this->propertiesTextExpression().' like ?', ["%{$search}%"])
                    ->orWhereHasMorph('causer', '*', function ($query) use ($search): void {
                        $query->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHasMorph('subject', '*', function ($query, string $type) use ($search): void {
                        $this->applySubjectSearch($query, $type, $search);
                    });
            });
        }

        $activities = $query->paginate($request->integer('per_page', 15));

        return response()->json($activities->through(fn (Activity $activity): array => $this->formatActivity($activity)));
    }

    private function propertiesTextExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'properties::text',
            'mysql', 'mariadb' => 'cast(properties as char)',
            default => 'properties',
        };
    }

    /**
     * @return array<string, class-string>
     */
    private function subjectTypes(): array
    {
        return [
            'cliente' => Cliente::class,
            'cotizacion' => Cotizacion::class,
            'cotizacion_item' => CotizacionItem::class,
            'cotizacion_costo' => CotizacionCostosAdicional::class,
            'cotizacion_item_proveedor' => CotizacionItemProveedor::class,
            'cotizacion_historial' => CotizacionHistorial::class,
            'cotizacion_modificacion' => CotizacionModificacion::class,
            'cotizacion_version' => CotizacionVersion::class,
            'producto' => Producto::class,
            'producto_externo' => ProductoExterno::class,
            'producto_serie' => ProductoSerie::class,
            'inventario_movimiento' => InventarioMovimiento::class,
            'proveedor' => Proveedor::class,
            'oc_recibida' => OcRecibida::class,
            'oc_recibida_item' => OcRecibidaItem::class,
            'oc_emitida' => OcEmitida::class,
            'oc_emitida_item' => OcEmitidaItem::class,
            'oc_documento_adicional' => OcDocumentoAdicional::class,
        ];
    }

    private function applySubjectSearch($query, string $type, string $search): void
    {
        match ($type) {
            Cliente::class => $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('ruc', 'like', "%{$search}%")
                ->orWhere('correo', 'like', "%{$search}%"),
            Cotizacion::class => $query->where('numero', 'like', "%{$search}%")
                ->orWhere('titulo', 'like', "%{$search}%")
                ->orWhereHas('cliente', fn ($clienteQuery) => $clienteQuery
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")),
            CotizacionItem::class => $query->where('descripcion', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhere('marca', 'like', "%{$search}%")
                ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                    ->where('numero', 'like', "%{$search}%")
                    ->orWhere('titulo', 'like', "%{$search}%")),
            CotizacionCostosAdicional::class => $query->where('tipo', 'like', "%{$search}%")
                ->orWhere('descripcion', 'like', "%{$search}%")
                ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                    ->where('numero', 'like', "%{$search}%")),
            CotizacionItemProveedor::class => $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('notas', 'like', "%{$search}%")
                ->orWhereHas('cotizacionItem.cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                    ->where('numero', 'like', "%{$search}%")),
            CotizacionHistorial::class,
            CotizacionModificacion::class,
            CotizacionVersion::class => $query->whereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                ->where('numero', 'like', "%{$search}%")
                ->orWhere('titulo', 'like', "%{$search}%")),
            Producto::class => $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhere('marca', 'like', "%{$search}%")
                ->orWhere('modelo', 'like', "%{$search}%")
                ->orWhere('factura_numero', 'like', "%{$search}%"),
            ProductoExterno::class => $query->where('descripcion', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhere('marca', 'like', "%{$search}%")
                ->orWhere('proveedor', 'like', "%{$search}%"),
            ProductoSerie::class => $query->where('serie', 'like', "%{$search}%")
                ->orWhere('factura_numero', 'like', "%{$search}%")
                ->orWhereHas('producto', fn ($productoQuery) => $productoQuery
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")),
            InventarioMovimiento::class => $query->where('tipo_movimiento', 'like', "%{$search}%")
                ->orWhere('documento_numero', 'like', "%{$search}%")
                ->orWhere('proveedor', 'like', "%{$search}%")
                ->orWhere('observacion', 'like', "%{$search}%")
                ->orWhereHas('producto', fn ($productoQuery) => $productoQuery
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%"))
                ->orWhereHas('productoSerie', fn ($serieQuery) => $serieQuery
                    ->where('serie', 'like', "%{$search}%")),
            Proveedor::class => $query->where('nombre', 'like', "%{$search}%")
                ->orWhere('ruc', 'like', "%{$search}%")
                ->orWhere('correo', 'like', "%{$search}%"),
            OcRecibida::class => $query->where('numero', 'like', "%{$search}%")
                ->orWhere('factura_numero', 'like', "%{$search}%")
                ->orWhere('cliente_nombre', 'like', "%{$search}%")
                ->orWhere('cliente_ruc', 'like', "%{$search}%")
                ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                    ->where('numero', 'like', "%{$search}%")),
            OcRecibidaItem::class => $query->where('descripcion', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhereHas('ocRecibida', fn ($ocQuery) => $ocQuery
                    ->where('numero', 'like', "%{$search}%")
                    ->orWhere('factura_numero', 'like', "%{$search}%")),
            OcEmitida::class => $query->where('numero', 'like', "%{$search}%")
                ->orWhere('proveedor', 'like', "%{$search}%")
                ->orWhere('cliente_nombre', 'like', "%{$search}%")
                ->orWhereHas('cotizacion', fn ($cotizacionQuery) => $cotizacionQuery
                    ->where('numero', 'like', "%{$search}%")),
            OcEmitidaItem::class => $query->where('descripcion', 'like', "%{$search}%")
                ->orWhere('codigo', 'like', "%{$search}%")
                ->orWhereHas('ocEmitida', fn ($ocQuery) => $ocQuery
                    ->where('numero', 'like', "%{$search}%")
                    ->orWhere('proveedor', 'like', "%{$search}%")),
            OcDocumentoAdicional::class => $query->where('nombre_original', 'like', "%{$search}%")
                ->orWhereHas('ocRecibida', fn ($ocQuery) => $ocQuery->where('numero', 'like', "%{$search}%"))
                ->orWhereHas('ocEmitida', fn ($ocQuery) => $ocQuery->where('numero', 'like', "%{$search}%")),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatActivity(Activity $activity): array
    {
        $properties = $activity->properties ?? collect();
        $attributes = $properties->get('attributes', []);
        $old = $properties->get('old', []);

        return [
            'id' => $activity->id,
            'accion' => $activity->event,
            'descripcion' => $activity->description,
            'entidad' => [
                'tipo' => $this->subjectLabel($activity->subject_type),
                'id' => $activity->subject_id,
                'nombre' => $this->subjectName($activity),
            ],
            'usuario' => [
                'id' => $activity->causer_id,
                'nombre' => $this->causerName($activity),
                'email' => $activity->causer?->email,
            ],
            'cambios' => $this->formatChanges($attributes, $old),
            'created_at' => $activity->created_at?->toISOString(),
        ];
    }

    private function subjectLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            Cliente::class => 'cliente',
            Cotizacion::class => 'cotizacion',
            CotizacionItem::class => 'cotizacion_item',
            CotizacionCostosAdicional::class => 'cotizacion_costo',
            CotizacionItemProveedor::class => 'cotizacion_item_proveedor',
            CotizacionHistorial::class => 'cotizacion_historial',
            CotizacionModificacion::class => 'cotizacion_modificacion',
            CotizacionVersion::class => 'cotizacion_version',
            Producto::class => 'producto',
            ProductoExterno::class => 'producto_externo',
            ProductoSerie::class => 'producto_serie',
            InventarioMovimiento::class => 'inventario_movimiento',
            Proveedor::class => 'proveedor',
            OcRecibida::class => 'oc_recibida',
            OcRecibidaItem::class => 'oc_recibida_item',
            OcEmitida::class => 'oc_emitida',
            OcEmitidaItem::class => 'oc_emitida_item',
            OcDocumentoAdicional::class => 'oc_documento_adicional',
            default => class_basename($subjectType ?? 'sistema'),
        };
    }

    private function subjectName(Activity $activity): ?string
    {
        $subject = $activity->subject;
        $attributes = $activity->properties?->get('attributes', []) ?? [];
        $old = $activity->properties?->get('old', []) ?? [];
        $values = array_merge($old, $attributes);

        return match ($activity->subject_type) {
            Cliente::class => $subject?->nombre ?? $values['nombre'] ?? null,
            Cotizacion::class => $subject?->numero ?? $values['numero'] ?? null,
            CotizacionItem::class => $subject?->descripcion ?? $values['descripcion'] ?? null,
            CotizacionCostosAdicional::class => $subject?->tipo ?? $values['tipo'] ?? null,
            CotizacionItemProveedor::class => $subject?->nombre ?? $values['nombre'] ?? null,
            CotizacionHistorial::class => $subject?->cotizacion?->numero ?? $values['cotizacion_id'] ?? null,
            CotizacionModificacion::class => $subject?->cotizacion?->numero ?? $values['cotizacion_id'] ?? null,
            CotizacionVersion::class => $subject?->numero_version ?? $values['numero_version'] ?? null,
            Producto::class => $subject?->sku ?? $subject?->nombre ?? $values['sku'] ?? $values['nombre'] ?? null,
            ProductoExterno::class => $subject?->descripcion ?? $values['descripcion'] ?? null,
            ProductoSerie::class => $subject?->serie ?? $values['serie'] ?? null,
            InventarioMovimiento::class => $subject?->documento_numero ?? $subject?->tipo_movimiento ?? $values['documento_numero'] ?? $values['tipo_movimiento'] ?? null,
            Proveedor::class => $subject?->nombre ?? $values['nombre'] ?? null,
            OcRecibida::class => $subject?->numero ?? $values['numero'] ?? null,
            OcRecibidaItem::class => $subject?->descripcion ?? $values['descripcion'] ?? null,
            OcEmitida::class => $subject?->numero ?? $values['numero'] ?? null,
            OcEmitidaItem::class => $subject?->descripcion ?? $values['descripcion'] ?? null,
            OcDocumentoAdicional::class => $subject?->nombre_original ?? $values['nombre_original'] ?? null,
            default => null,
        };
    }

    private function causerName(Activity $activity): ?string
    {
        if (! $activity->causer) {
            return null;
        }

        return trim("{$activity->causer->nombres} {$activity->causer->apellidos}");
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @return array<int, array<string, mixed>>
     */
    private function formatChanges(array $attributes, array $old): array
    {
        if ($attributes === [] && $old !== []) {
            $attributes = array_fill_keys(array_keys($old), null);
        }

        return collect($attributes)
            ->map(fn (mixed $newValue, string $field): array => [
                'campo' => $field,
                'antes' => $old[$field] ?? null,
                'despues' => $newValue,
            ])
            ->values()
            ->all();
    }
}
