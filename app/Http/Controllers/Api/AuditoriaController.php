<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CotizacionCostosAdicional;
use App\Models\CotizacionItem;
use App\Models\CotizacionItemProveedor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'event' => 'nullable|in:created,updated,deleted',
            'tipo' => 'nullable|in:cliente,cotizacion,cotizacion_item,cotizacion_costo,cotizacion_item_proveedor',
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
                    ->orWhereHasMorph('causer', '*', function ($query) use ($search): void {
                        $query->where('nombres', 'like', "%{$search}%")
                            ->orWhere('apellidos', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $activities = $query->paginate($request->integer('per_page', 15));

        return response()->json($activities->through(fn (Activity $activity): array => $this->formatActivity($activity)));
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
        ];
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
