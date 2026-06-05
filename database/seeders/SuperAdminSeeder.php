<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'luis.lopez@willatec.com'],
            [
                'nombres' => 'Luis',
                'apellidos' => 'Lopez Salazar',
                'password' => Hash::make('$LuisLopez'),
            ]
        );

        $role = Role::findOrFail(1);

        $user->syncRoles([$role]);
    }
}
