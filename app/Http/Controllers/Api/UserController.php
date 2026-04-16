<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // Listar usuarios 
    public function index(){
        $users= User::with(['profile', 'roles'])->get();

        return response()->json($users);
    }

    //Ver detalle
    public function show($id){
        $user = User::with(['profile', 'roles'])->findOrFail($id);

        return response()->json($user);
    }

    //Actualizar Usuario
    public function update(Request $request, $id){
        $user = User::findOrFail($id);

        $request->validate([
            'nombres'=> 'required',
            'apellidos' => 'required',
            'email' => "required|email|unique:users,email,$id",
            'role' => 'required|exists:roles,name',
        ]);

        DB::transaction(function() use ($request, $user){

            $user->update([
                'nombres'=> $request->nombres,
                'apellidos'=> $request->apellidos,
                'email'=> $request->email,
            ]);

            //Actualizar rol
            $user->syncRoles([$request->role]);

            //Actualizar Profile
            $user->profile()->updateOrCreate(
                ['user_id'=> $user->id],
                [
                    'telefono'=>$request->telefono,
                    'dni'=> $request->dni,
                    'cargo'=> $request->cargo,
                ]
            );
        });

        return response()->json([
            'message'=>'Usuario actualizado correctamente',
            'user'=> $user->loadMissing('profile', 'roles')
        ]);
    }

    //Desactivar usuario
    public function desactivar($id){
        $user = User::findOrFail($id);

        $user->update([
            'activo'=>false
        ]);

        return response()->json([
            'message'=> 'Usuario desactivado correctamente'
        ]);
        
    }

    //Reactivar usuario
    public function activar($id){
        $user = User::findOrFail($id);

        $user->update([
            'activo'=> true
        ]);

        return response()->json([
            'message'=> 'Usuario activado correctamente'
        ]);
    }
}
