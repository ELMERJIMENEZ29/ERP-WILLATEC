<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;

class ProductoController extends Controller
{
    //Listar Productos
    public function index(Request $request){

        $query = Producto::query();

        if($request->has('activo')){
            $query->where('activo', $request->activo);
        }

        return response()->json($query->get());
    }

    //Ver detalle
    public function show($id){
        $producto = Producto::findOrFail($id);

        if(!$producto){
        return response()->json([
            'message' => 'Producto no encontrado'
        ], 404);
        }
    }

    //Crear producto
    public function store(Request $request){

        $request->validate([
            'nombre'=> 'required|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'precio_referencial' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
        ]);

        $producto = Producto::create($request->only([
            'nombre',
            'marca',
            'modelo',
            'codigo',
            'descripcion',
            'precio_referencial',
            'unidad_medida',
        ]));

        return response()->json([
            'message'=> 'Producto creado correctamente',
            'producto' => $producto
        ]);
    }

    //Actualizar producto
    public function update(Request $request,$id){

        $producto = Producto::findOrFail($id);

        $request->validate([
            'nombre'=> 'required|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'precio_referencial' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
        ]);

        $producto->update($request->only([
            'nombre',
            'marca',
            'modelo',
            'codigo',
            'descripcion',
            'precio_referencial',
            'unidad_medida',
        ]));

        return response()->json([
            'message'=> 'Producto actualizado correctamente',
            'producto' => $producto,
        ]);
    }

    //Desactivar Producto
    public function desactivar($id){
        $producto = Producto::findOrFail($id);

        $producto->update([
            'activo' => false
        ]);

        return response()->json([
            'message' => 'Producto desactivado correctamente'
        ]);
    }

    //Activar producto
    public function activar($id){
        $producto = Producto::findOrFail($id);

        $producto->update([
            'activo' => true
        ]);

        return response()->json([
            'message' => 'Producto activado correctamente'
        ]);
    }
}
