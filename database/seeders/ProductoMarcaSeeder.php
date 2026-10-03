<?php

namespace Database\Seeders;

use App\Models\ProductoMarca;
use Illuminate\Database\Seeder;

class ProductoMarcaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductoMarca::create([
            'nombre' => 'Apple',
        ]);
    }
}
