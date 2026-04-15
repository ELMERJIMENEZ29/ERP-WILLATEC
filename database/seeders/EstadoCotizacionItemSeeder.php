<?php

namespace Database\Seeders;

use App\Models\EstadoCotizacionItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EstadoCotizacionItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EstadoCotizacionItem::insert([
            ['nombre' => 'pendiente'],
            ['nombre' => 'aprobado'],
            ['nombre' => 'rechazado'],
        ]);
    }
}
