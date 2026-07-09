<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:150',
            'activo' => 'nullable|in:true,false,1,0',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Proveedor::query()->orderBy('nombre');

        if (array_key_exists('activo', $validated)) {
            $query->where('activo', filter_var($validated['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($query) use ($search): void {
                $query->where('nombre', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('contacto', 'like', "%{$search}%")
                    ->orWhere('correo', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 100))
        );
    }

    public function store(Request $request)
    {
        $proveedor = Proveedor::create($this->validatePayload($request));

        return response()->json([
            'message' => 'Proveedor registrado correctamente',
            'proveedor' => $proveedor,
        ], 201);
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $proveedor->update($this->validatePayload($request));

        return response()->json([
            'message' => 'Proveedor actualizado correctamente',
            'proveedor' => $proveedor->refresh(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'contacto' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
            'direccion' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string',
            'activo' => 'nullable|boolean',
        ]);
    }
}
