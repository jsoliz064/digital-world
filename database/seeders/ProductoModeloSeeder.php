<?php

namespace Database\Seeders;

use App\Enums\ProductoAlmacenamiento;
use App\Models\ProductoModelo;
use App\Models\ProductoModeloAlmacenamiento;
use Illuminate\Database\Seeder;

class ProductoModeloSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            'iPhone XR',
            'iPhone 11',
            'iPhone 11 Pro',
            'iPhone 11 Pro Max',
            'iPhone 12',
            'iPhone 12 mini',
            'iPhone 12 Pro',
            'iPhone 12 Pro Max',
            'iPhone 13',
            'iPhone 13 mini',
            'iPhone 13 Pro',
            'iPhone 13 Pro Max',
            'iPhone 14',
            'iPhone 14 Plus',
            'iPhone 14 Pro',
            'iPhone 14 Pro Max',
            'iPhone 15',
            'iPhone 15 Plus',
            'iPhone 15 Pro',
            'iPhone 15 Pro Max',
            'iPhone 16',
            'iPhone 16 Plus',
            'iPhone 16 Pro',
            'iPhone 16 Pro Max',
        ];

        $almacenamientos = ProductoAlmacenamiento::cases();

        // Precio base para el modelo más antiguo (iPhone XR)
        $precioBase = 1000;

        foreach ($models as $index => $model) {
            $productoModelo = ProductoModelo::create([
                'nombre' => $model,
                'producto_categoria_id' => 1,
            ]);

            // Incremento de precio por generación de modelo
            $incrementoModelo = $index * 500;

            foreach ($almacenamientos as $almacenamiento) {
                // Incremento de precio por almacenamiento
                $incrementoAlmacenamiento = match ($almacenamiento->value) {
                    '64GB' => 0,
                    '128GB' => 100,
                    '256GB' => 200,
                    '512GB' => 300,
                    '1TB' => 400,
                    default => 0,
                };

                // Precio final
                $precio = $precioBase + $incrementoModelo + $incrementoAlmacenamiento;

                // Para los modelos más nuevos (ej. iPhone 16), aumentar más el precio
                if ($index >= 20) { // iPhone 16 en adelante
                    $precio += 500;
                } elseif ($index >= 16) { // iPhone 15 en adelante
                    $precio += 300;
                } elseif ($index >= 12) { // iPhone 14 en adelante
                    $precio += 150;
                }

                ProductoModeloAlmacenamiento::create([
                    'almacenamiento' => $almacenamiento->value,
                    'precio' => $precio,
                    'producto_modelo_id' => $productoModelo->id
                ]);
            }
        }
    }
}
