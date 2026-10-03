<?php

namespace Database\Seeders;

use App\Models\ProductoCategoria;
use Illuminate\Database\Seeder;

class ProductoCategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductoCategoria::create([
            'nombre' => 'iPhone',
            'producto_marca_id' => 1,
        ]);
        
        ProductoCategoria::create([
            'nombre' => 'Apple Watch',
            'producto_marca_id' => 1,
        ]);
        
    }
}
