<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'nombres' => 'Test',
            'apellidos' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Seeders
        $this->call([
            EstadoCotizacionSeeder::class,
            RoleSeeder::class,
            ConsolidateRolePermissionsSeeder::class,
            ConsolidateRolePermissionsV2Seeder::class,
            ConsolidateRolePermissionsV3Seeder::class,
            ConsolidateRolePermissionsV4Seeder::class,
            ConsolidateRolePermissionsV5Seeder::class,
            ConsolidateRolePermissionsV6Seeder::class,
            ConsolidateRolePermissionsV7Seeder::class,
            EstadoCotizacionItemSeeder::class,
            PlataformaSeeder::class,
            MonedaSeeder::class,
            CategoriaSeeder::class,
            PlantillaSeeder::class,
            TipoClienteSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
