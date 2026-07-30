<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hosting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HostingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'estado' => ['nullable', Rule::in(['VIGENTE', 'POR VENCER', 'VENCIDO'])],
            'suscripcion' => ['nullable', Rule::in(['ANUAL', 'MENSUAL'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Hosting::with('clienteRelacionado:id,nombre,ruc,correo')
            ->orderBy('fecha_renovacion')
            ->orderBy('empresa');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('empresa', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('dominio', 'like', "%{$search}%")
                    ->orWhere('plan', 'like', "%{$search}%")
                    ->orWhere('contacto', 'like', "%{$search}%")
                    ->orWhere('cliente', 'like', "%{$search}%")
                    ->orWhere('correo_hosting', 'like', "%{$search}%")
                    ->orWhereHas('clienteRelacionado', function ($clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%")
                            ->orWhere('correo', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['suscripcion'])) {
            $query->where('suscripcion', $validated['suscripcion']);
        }

        if (! empty($validated['estado'])) {
            $today = Carbon::today();

            match ($validated['estado']) {
                'VENCIDO' => $query->whereDate('fecha_renovacion', '<', $today),
                'POR VENCER' => $query
                    ->whereDate('fecha_renovacion', '>=', $today)
                    ->whereDate('fecha_renovacion', '<=', $today->copy()->addDays(30)),
                'VIGENTE' => $query->whereDate('fecha_renovacion', '>', $today->copy()->addDays(30)),
            };
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 10))
        );
    }

    public function store(Request $request)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            $payload['suscripcion']
        );

        $hosting = Hosting::create($payload);

        return response()->json([
            'message' => 'Hosting registrado correctamente',
            'hosting' => $hosting->load('clienteRelacionado:id,nombre,ruc,correo'),
        ], 201);
    }

    public function show(Hosting $hosting)
    {
        return response()->json(
            $hosting->load('clienteRelacionado:id,nombre,ruc,correo')
        );
    }

    public function update(Request $request, Hosting $hosting)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            $payload['suscripcion']
        );

        $hosting->update($payload);

        return response()->json([
            'message' => 'Hosting actualizado correctamente',
            'hosting' => $hosting->refresh()->load('clienteRelacionado:id,nombre,ruc,correo'),
        ]);
    }

    public function destroy(Hosting $hosting)
    {
        $hosting->delete();

        return response()->json([
            'message' => 'Hosting eliminado correctamente',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        foreach (['empresa', 'ruc', 'dominio', 'plan', 'contacto', 'cliente', 'correo_hosting'] as $field) {
            $request->merge([
                $field => $this->nullableTrim($request->input($field)),
            ]);
        }

        return $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'empresa' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'dominio' => 'required|string|max:255',
            'plan' => 'required|string|max:255',
            'suscripcion' => ['required', Rule::in(['ANUAL', 'MENSUAL'])],
            'fecha_inicio' => 'required|date',
            'contacto' => 'nullable|string|max:255',
            'cliente' => 'nullable|string|max:255',
            'correo_hosting' => 'nullable|email|max:255',
        ]);
    }

    private function nullableTrim(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function calculateFechaRenovacion(string $fechaInicio, string $suscripcion): string
    {
        $fecha = Carbon::parse($fechaInicio);

        return ($suscripcion === 'MENSUAL'
            ? $fecha->addMonthNoOverflow()
            : $fecha->addYearNoOverflow()
        )
            ->subDay()
            ->toDateString();
    }
}
