<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmpresaConfiguracion;
use Illuminate\Http\Request;

class EmpresaConfiguracionController extends Controller
{
    public function show()
    {
        return response()->json(EmpresaConfiguracion::current());
    }

    public function update(Request $request)
    {
        $payload = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'ruc' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
        ]);

        $configuracion = EmpresaConfiguracion::current();
        $configuracion->update($payload);

        return response()->json([
            'message' => 'Datos de empresa guardados correctamente',
            'empresa' => $configuracion->refresh(),
        ]);
    }
}
