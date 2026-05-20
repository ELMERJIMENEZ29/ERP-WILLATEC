<?php

namespace Database\Seeders;

use App\Models\Plantilla;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlantillaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plantilla::insert([
            [
                'nombre' => 'WILLATEC DOLARES',
                'incluye_igv'=> false,
                'formato_pdf' => 'willatec-dolares', 
                'activo' => true 
            ],
            [
                'nombre' => 'WILLATEC SOLES',
                'incluye_igv'=> false,
                'formato_pdf' => 'willatec-soles', 
                'activo' => true 
            ],
            [
                'nombre' => 'WILLATEC SOLES-ESTADO',
                'incluye_igv'=> true,
                'formato_pdf' => 'willatec-soles-estado', 
                'activo' => true 
            ],
            [
                'nombre' => 'GSD PRIVADO',
                'incluye_igv'=> false,
                'formato_pdf' => 'gsd-privado', 
                'activo' => true 
            ],
            [
                'nombre' => 'GSD ESTADO',
                'incluye_igv'=> true,
                'formato_pdf' => 'gsd-estado', 
                'activo' => true 
            ],
        ]);
    }
}
