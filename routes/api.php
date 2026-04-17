<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/user', fn(Request $request)=> $request->user());

    Route::get('/roles', function () {return Role::select('id','name')->get();});

    //USUARIOS
    Route::post('/register',[AuthController::class, 'register'])->middleware(['role:superadmin']);

    Route::get('/users', [UserController::class, 'index'])->middleware('role:superadmin|admin');

    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('role:superadmin|admin');

    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('role:superadmin');

    Route::patch('/users/{id}/desactivar', [UserController::class, 'desactivar'])->middleware('role:superadmin');

    Route::patch('/users/{id}/activar', [UserController::class, 'activar'])->middleware('role:superadmin');
});

Route::prefix('productos')->middleware('auth:sanctum')->group(function(){
    //PRODUCTOS
    Route::get('/', [ProductoController::class, 'index']);
    Route::get('/{id}', [ProductoController::class, 'show']);

    Route::post('/', [ProductoController::class, 'store'])->middleware('role:superadmin|admin');

    Route::put('/{id}', [ProductoController::class, 'update'])->middleware('role:superadmin|admin');

    Route::patch('/{id}/desactivar', [ProductoController::class, 'desactivar'])->middleware('role:superadmin');

    Route::patch('/{id}/activar', [ProductoController::class, 'activar'])->middleware('role:superadmin');
});

