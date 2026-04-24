<?php

namespace Database\Seeders;

use App\Models\EstadoCotizacion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoCotizacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EstadoCotizacion::insert([
            ['nombre' => 'borrador'],
            ['nombre' => 'enviada'],
            ['nombre' => 'parcialmente_aprobada'],
            ['nombre' => 'aprobada'],
            ['nombre' => 'rechazada'],
            ['nombre' => 'oc_registrada']
        ]);    
    }
}
