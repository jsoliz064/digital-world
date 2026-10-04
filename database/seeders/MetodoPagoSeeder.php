<?php

namespace Database\Seeders;

use App\Models\MetodoPago;
use Illuminate\Database\Seeder;

/** Los metodos de pago de arranque (docs/00). firstOrCreate: se puede volver a correr. */
class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Efectivo', 'QR', 'Transferencia', 'Tarjeta'] as $orden => $nombre) {
            MetodoPago::firstOrCreate(['nombre' => $nombre], ['orden' => $orden + 1, 'activo' => true]);
        }
    }
}
