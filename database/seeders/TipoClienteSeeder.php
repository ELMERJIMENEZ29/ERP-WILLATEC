<?php

namespace Database\Seeders;

use App\Models\TipoCliente;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TipoClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TipoCliente::updateOrCreate(
            ['nombre' => 'Prospecto'],
        );

        TipoCliente::updateOrCreate(
            ['nombre' => 'Activo'],
        );

        TipoCliente::updateOrCreate(
            ['nombre' => 'Suspendido'],
        );

        TipoCliente::updateOrCreate(
            ['nombre' => 'Inactivo'],
        );
    }
}
