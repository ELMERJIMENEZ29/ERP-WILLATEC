<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\CotizacionController;
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

Route::prefix('cotizaciones')->middleware('auth:sanctum')->group(function(){
    Route::get('/', [CotizacionController::class, 'index']);
    Route::get('/{id}', [CotizacionController::class, 'show']);

    Route::post('/', [CotizacionController::class, 'store']);
    Route::put('/{id}', [CotizacionController::class, 'update']);

    //Recalcular
    Route::patch('/{id}/recalcular', [CotizacionController::class, 'recalcular']);

    //Costos adicionales
    Route::delete('/costos/{id}', [CotizacionController::class, 'deleteCosto']);
    Route::post('/{id}/costos', [CotizacionController::class, 'addCosto']);

    //Items
    Route::post('/{id}/items', [CotizacionController::class, 'addItem']);
    Route::put('/items/{id}', [CotizacionController::class, 'updateItem']);
    Route::delete('/items/{id}', [CotizacionController::class, 'deleteItem']);
});

