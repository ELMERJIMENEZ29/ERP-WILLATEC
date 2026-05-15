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

        return response()->json($query->paginate($request->per_page ?? 10));
    }

    //Ver detalle
    public function show( int $id){
        $producto = Producto::findOrFail($id);

        if(!$producto){
        return response()->json([
            'message' => 'Producto no encontrado'
        ], 404);
        }

        return response()->json($producto);
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
            'stock' => 'nullable|integer|min:0',
            'categoria_id' => 'nullable|exists:categorias,id',
        ]);

        $producto = Producto::create([
            'nombre' => $request->nombre,
            'marca' => $request->marca,
            'modelo' => $request->modelo,
            'codigo' => $request->codigo,
            'descripcion' => $request->descripcion,
            'precio_referencial' => $request->precio_referencial,
            'unidad_medida' => $request->unidad_medida,
            'stock' => $request->stock ?? 0,
            'categoria_id' => $request->categoria_id,
            'activo' => true,
        ]);

        return response()->json([
            'message'=> 'Producto creado correctamente',
            'producto' => $producto
        ],201);
    }

    //Actualizar producto
    public function update(Request $request,int $id){

        $producto = Producto::findOrFail($id);

        $request->validate([
            'nombre'=> 'required|string|max:255',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'codigo' => 'nullable|string|max:100',
            'descripcion' => 'nullable|string',
            'precio_referencial' => 'nullable|numeric|min:0',
            'unidad_medida' => 'nullable|string|max:50',
            'activo' => 'nullable|boolean',
            'stock' => 'nullable|integer|min:0',
            'categoria_id' => 'nullable|exists:categorias,id',
        ]);

        $producto->update($request->only([
            'nombre',
            'marca',
            'modelo',
            'codigo',
            'descripcion',
            'precio_referencial',
            'unidad_medida',
            'activo',
            'stock',
            'categoria_id',
        ]));

        return response()->json([
            'message'=> 'Producto actualizado correctamente',
            'producto' => $producto,
        ]);
    }

    //Eliminar Producto
    public function destroy(int $id){
        $producto = Producto::findOrFail($id);

        if(!$producto){
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $producto->delete();

        return response()->json([
            'message' => 'Producto eliminado correctamente',
        ]);
    }
}       
