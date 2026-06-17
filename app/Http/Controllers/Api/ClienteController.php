<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    // Listar Clientes
    public function index(Request $request)
    {
        $request->validate([
            'estado' => 'nullable|string|max:50',
            'tipo_cliente_id' => 'nullable|integer|exists:tipo_clientes,id',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Cliente::with(['tipoCliente', 'moneda']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado')->toString());
        }

        if ($request->filled('tipo_cliente_id')) {
            $query->where('tipo_cliente_id', $request->integer('tipo_cliente_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%")
                    ->orWhere('telefono', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query
                ->orderBy('nombre', 'asc')
                ->paginate($request->integer('per_page', 10))
        );
    }

    // Crear cliente
    public function store(Request $request)
    {
        $this->normalizeOptionalContactFields($request);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:11',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',

            'tipo_cliente_id' => 'nullable|exists:tipo_clientes,id',
            'moneda_id' => 'nullable|exists:monedas,id',
        ]);

        $cliente = Cliente::create([
            'nombre' => $request->nombre,
            'ruc' => $request->ruc,
            'correo' => $request->correo,
            'telefono' => $request->telefono,
            'direccion' => $request->direccion,
            'estado' => $request->estado ?? 'activo',
            'tipo_cliente_id' => $request->tipo_cliente_id,
            'moneda_id' => $request->moneda_id,
        ]);

        return response()->json([
            'message' => 'Cliente creado correctamente',
            'cliente' => $cliente,
        ], 201);
    }

    // Ver detalle
    public function show(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        if (! $cliente) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        return response()->json($cliente);
    }

    // Actualizar cliente
    public function update(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);

        if (! $cliente) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $this->normalizeOptionalContactFields($request);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:11',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',

            'tipo_cliente_id' => 'nullable|exists:tipo_clientes,id',
            'moneda_id' => 'nullable|exists:monedas,id',

            'estado' => 'nullable|in:activo,inactivo',
        ]);

        $cliente->update($request->only([
            'nombre',
            'ruc',
            'correo',
            'telefono',
            'tipo_cliente_id',
            'moneda_id',
            'estado',
        ]));

        return response()->json([
            'message' => 'Cliente actualizado correctamente',
            'cliente' => $cliente,
        ]);
    }

    // Eliminar cliente
    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);

        if (! $cliente) {
            return response()->json([
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    private function normalizeOptionalContactFields(Request $request): void
    {
        foreach (['correo', 'telefono'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            $request->merge([
                $field => $value === '' ? null : $value,
            ]);
        }
    }
}
