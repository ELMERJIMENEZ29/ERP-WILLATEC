<?php

namespace Database\Seeders;

use App\Models\Plataforma;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlataformaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plataforma::insert([
            ['nombre' => 'correo'],
            ['nombre' => 'wherex'],
            ['nombre' => 'licitacion'],
            ['nombre' => 'via_telefonica'],
        ]);
    }
}
