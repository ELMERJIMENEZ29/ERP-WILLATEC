<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Licencia;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenciaController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'estado' => ['nullable', Rule::in(['VIGENTE', 'POR VENCER', 'VENCIDO'])],
            'suscripcion_meses' => 'nullable|integer|min:1|max:240',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Licencia::with('cliente:id,nombre,ruc,correo')
            ->orderBy('fecha_renovacion')
            ->orderBy('empresa');

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('empresa', 'like', "%{$search}%")
                    ->orWhere('producto', 'like', "%{$search}%")
                    ->orWhere('correo_licencia', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($clienteQuery) use ($search): void {
                        $clienteQuery->where('nombre', 'like', "%{$search}%")
                            ->orWhere('ruc', 'like', "%{$search}%")
                            ->orWhere('correo', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['suscripcion_meses'])) {
            $query->where('suscripcion_meses', $validated['suscripcion_meses']);
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
            (int) $payload['suscripcion_meses']
        );

        $licencia = Licencia::create($payload);

        return response()->json([
            'message' => 'Licencia registrada correctamente',
            'licencia' => $licencia->load('cliente:id,nombre,ruc,correo'),
        ], 201);
    }

    public function show(Licencia $licencia)
    {
        return response()->json(
            $licencia->load('cliente:id,nombre,ruc,correo')
        );
    }

    public function update(Request $request, Licencia $licencia)
    {
        $payload = $this->validatePayload($request);
        $payload['fecha_renovacion'] = $this->calculateFechaRenovacion(
            $payload['fecha_inicio'],
            (int) $payload['suscripcion_meses']
        );

        $licencia->update($payload);

        return response()->json([
            'message' => 'Licencia actualizada correctamente',
            'licencia' => $licencia->refresh()->load('cliente:id,nombre,ruc,correo'),
        ]);
    }

    public function destroy(Licencia $licencia)
    {
        $licencia->delete();

        return response()->json([
            'message' => 'Licencia eliminada correctamente',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $request->merge([
            'empresa' => trim((string) $request->input('empresa', '')),
            'producto' => trim((string) $request->input('producto', '')),
            'correo_licencia' => $this->nullableTrim($request->input('correo_licencia')),
        ]);

        return $request->validate([
            'cliente_id' => 'nullable|integer|exists:clientes,id',
            'empresa' => 'required|string|max:255',
            'producto' => 'required|string|max:255',
            'cantidad' => 'required|integer|min:1|max:100000',
            'suscripcion_meses' => 'required|integer|min:1|max:240',
            'correo_licencia' => 'nullable|email|max:255',
            'fecha_inicio' => 'required|date',
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

    private function calculateFechaRenovacion(string $fechaInicio, int $meses): string
    {
        return Carbon::parse($fechaInicio)
            ->addMonthsNoOverflow($meses)
            ->subDay()
            ->toDateString();
    }
}
