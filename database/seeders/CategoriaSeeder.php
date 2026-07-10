<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            1 => 'LAPTOPS',
            2 => 'ACCESORIOS',
            3 => 'PERIFÉRICOS',
            4 => 'COMPUTADORAS',
            5 => 'LICENCIAS',
            6 => 'SERVIDORES',
            7 => 'GADGETS',
            8 => 'SUMINISTROS',
            9 => 'REDES',
            10 => 'SEGURIDAD',
            11 => 'COMPONENTES',
            12 => 'ALMACENAMIENTO',
            13 => 'IMPRESORAS',
        ];

        foreach ($categorias as $id => $nombre) {
            Categoria::updateOrCreate(
                ['id' => $id],
                ['nombre' => $nombre],
            );
        }
    }
}
