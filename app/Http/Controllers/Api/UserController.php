<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class UserController extends Controller
{
    // Listar usuarios 
    public function index(){
        $users= User::with(['profile', 'roles'])->get()->map(fn (User $user) => $this->withSecurityState($user));

        return response()->json($users);
    }

    //Ver detalle
    public function show(int $id){
        $user = User::with(['profile', 'roles'])->findOrFail($id);

        return response()->json($this->withSecurityState($user));
    }

    //Actualizar Usuario
    public function update(Request $request, $id){
        $user = User::findOrFail($id);

        $request->validate([
            'nombres'=> 'required',
            'apellidos' => 'required',
            'email' => "required|email|unique:users,email,$id",
            'role' => 'required|exists:roles,name',
            'activo'=> 'boolean',
        ]);

        DB::transaction(function() use ($request, $user){

            $user->update([
                'nombres'=> $request->nombres,
                'apellidos'=> $request->apellidos,
                'email'=> $request->email,
                'activo'=> $request->activo ?? $user->activo,
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
    public function desactivar(int $id){
        $user = User::findOrFail($id);

        $user->update([
            'activo'=>false
        ]);

        return response()->json([
            'message'=> 'Usuario desactivado correctamente'
        ]);
        
    }

    //Reactivar usuario
    public function activar(int $id){
        $user = User::findOrFail($id);

        $user->update([
            'activo'=> true
        ]);

        return response()->json([
            'message'=> 'Usuario activado correctamente'
        ]);
    }

    public function resetTwoFactor(Request $request, int $id)
    {
        $admin = $this->validateAdminSecurity($request);
        $target = User::findOrFail($id);

        $target->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'requires_password_change' => true,
        ])->save();
        $target->tokens()->delete();

        activity('auditoria')
            ->causedBy($admin)
            ->performedOn($target)
            ->withProperties(['accion' => 'restablecer_2fa', 'motivo' => $request->input('motivo')])
            ->log('Restablecimiento administrativo de 2FA');

        return response()->json([
            'message' => '2FA restablecido. El usuario debera configurarlo nuevamente al ingresar.',
            'user' => $this->withSecurityState($target->fresh(['profile', 'roles'])),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request, int $id)
    {
        $admin = $this->validateAdminSecurity($request);
        $target = User::findOrFail($id);

        abort_if(! $target->two_factor_secret || ! $target->two_factor_confirmed_at, 422, 'El usuario no tiene 2FA activo.');

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->all();
        $target->forceFill(['two_factor_recovery_codes' => json_encode($codes)])->save();

        activity('auditoria')
            ->causedBy($admin)
            ->performedOn($target)
            ->withProperties(['accion' => 'regenerar_codigos_2fa', 'motivo' => $request->input('motivo')])
            ->log('Regeneracion administrativa de codigos 2FA');

        return response()->json([
            'message' => 'Codigos de recuperacion regenerados.',
            'recovery_codes' => $codes,
        ]);
    }

    private function validateAdminSecurity(Request $request): User
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'code' => 'required|string|size:6',
            'motivo' => 'required|string|max:500',
        ]);
        $admin = $request->user();

        abort_if(! $admin || ! $admin->hasRole('superadmin'), 403, 'Solo un superadmin puede realizar esta accion.');
        abort_if(! Hash::check($validated['password'], $admin->password), 422, 'La contrasena del superadmin es incorrecta.');
        abort_if(! $admin->two_factor_secret || ! $admin->two_factor_confirmed_at, 422, 'El superadmin debe tener 2FA activo.');

        $google2fa = new Google2FA();
        $secret = Crypt::decryptString($admin->two_factor_secret);
        abort_if(! $google2fa->verifyKey($secret, $validated['code']), 422, 'El codigo 2FA del superadmin es incorrecto.');

        return $admin;
    }

    private function withSecurityState(User $user): User
    {
        $user->setAttribute('two_factor_enabled', filled($user->two_factor_secret) && filled($user->two_factor_confirmed_at));
        return $user;
    }
}
