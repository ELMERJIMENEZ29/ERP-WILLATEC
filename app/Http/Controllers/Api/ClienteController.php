<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;

class ClienteController extends Controller
{
    //Listar Clientes
    public function index(Request $request){

        $query = Cliente::query();

        if($request->has('activo')){
            $query->where('activo', $request->activo);
        }

        return response()->json($query->paginate($request->per_page ?? 10));
    }

    //Crear cliente
    public function store(Request $request){

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
            'cliente' => $cliente
        ],201);
    }

    //Ver detalle
    public function show(int $id){
        $cliente = Cliente::findOrFail($id);

        if(!$cliente){
        return response()->json([
            'message' => 'Cliente no encontrado'
        ], 404);
        }

        return response()->json($cliente);
    }

    //Actualizar cliente
    public function update(Request $request, int $id){
        $cliente = Cliente::findOrFail($id);

        if(!$cliente){
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'ruc' => 'nullable|string|max:11',
            'correo' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',

            'tipo_cliente_id' => 'nullable|exists:tipo_clientes,id',
            'moneda_id' => 'nullable|exists:monedas,id',

            'estado'=>'nullable|in:activo,inactivo',
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
            'cliente' => $cliente
        ]);
    }

    //Eliminar cliente
    public function destroy(int $id){
        $cliente = Cliente::findOrFail($id);

        if(!$cliente){
            return response()->json([
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente'
        ]);
    }
}