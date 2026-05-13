<?php

namespace Database\Seeders;

use App\Models\Moneda;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MonedaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Moneda::updateOrCreate(
            ['codigo' => 'PEN'],
            ['simbolo' => 'S/']
        );

        Moneda::updateOrCreate(
            ['codigo' => 'USD'],
            ['simbolo' => '$']
        );

        Moneda::updateOrCreate(
            ['codigo' => 'EUR'],
            ['simbolo' => '€']
        );
    }
}
