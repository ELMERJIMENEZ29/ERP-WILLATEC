<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Categoria;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Categoria::updateOrCreate(
            ['nombre' => 'Tecnología'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Hogar'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Deportes'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Salud'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Alimentos y Bebidas'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Estanterías / Racks'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Repuestos impresoras'],
        );
        Categoria::updateOrCreate(
            ['nombre' => 'Otros'],
        );
    }
}
