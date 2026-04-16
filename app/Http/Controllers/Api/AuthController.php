<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'=>'required|email',
            'password' => 'required'
        ]);

        if(!Auth::attempt([
            'email'=>$request->email,
            'password'=>$request->password,
            'activo'=> true,
        ])){
            return response()->json([
                'message'=> 'Credenciales incorrectas o usuario inactivo'
            ],401);
        }

        $user = Auth::user();

        //Eliminacion de token antiguos
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('profile', 'roles'),
            'token' => $token,
        ]);
    }

    public function register(Request $request){
        $request->validate([
            'nombres'=> 'required',
            'apellidos' => 'required',
            'email'=> 'required|email|unique:users',
            'password'=> 'required|min:6|confirmed',
            'role'=> 'required|exists:roles,name',
        ]);

        $user = User::create([
            'nombres'=> $request->nombres,
            'apellidos'=>$request->apellidos,
            'email'=> $request->email,
            'password'=> Hash::make($request->password),
        ]);

        $user-> assignRole($request->role);

        Profile::create([
            'user_id'=>$user->id,
            'telefono'=> $request->telefono ?? null,
            'dni'=> $request->dni ?? null,
            'cargo'=> $request->cargo ?? null,
        ]);

        return response()->json([
            'message'=> 'Usuario creado correctamente',
            'user'=> $user->loadMissing('profile', 'roles')
        ]);
    }
}
