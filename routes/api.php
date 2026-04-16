<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function(){
    Route::get('/user', fn(Request $request)=> $request->user());

    Route::get('/roles', function () {
        return Role::select('id','name')->get();
    });

    Route::post('/register',[AuthController::class, 'register'])->middleware(['role:superadmin']);
});

