<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        //REPUESTOS CATEGORIAS
        Permission::firstOrCreate(['name' => 'repuesto-categoria.index']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.create']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.edit']);
        Permission::firstOrCreate(['name' => 'repuesto-categoria.delete']);

        //PRODUCTOS
        // Sueltos a proposito: se asignan a mano desde Editar Rol, igual que
        // repuesto.tipo-cambio-masivo.
        Permission::firstOrCreate(['name' => 'producto.estado-masivo']);
        Permission::firstOrCreate(['name' => 'producto.trabajo-externo']);

        //REPUESTOS
        Permission::firstOrCreate(['name' => 'repuesto.historial']);
        Permission::firstOrCreate(['name' => 'repuesto.tipo-cambio-masivo']);
        Permission::firstOrCreate(['name' => 'repuesto.transferir']);

        //ACCESORIOS
        Permission::firstOrCreate(['name' => 'accesorio.index']);
        Permission::firstOrCreate(['name' => 'accesorio.create']);
        Permission::firstOrCreate(['name' => 'accesorio.edit']);
        Permission::firstOrCreate(['name' => 'accesorio.delete']);

        //CLIENTES
        // El modulo nace entero aqui: hasta ahora el cliente era un texto suelto
        // en cada venta y no habia nada que permisar.
        Permission::firstOrCreate(['name' => 'cliente.index']);
        Permission::firstOrCreate(['name' => 'cliente.create']);
        Permission::firstOrCreate(['name' => 'cliente.edit']);
        Permission::firstOrCreate(['name' => 'cliente.delete']);
        Permission::firstOrCreate(['name' => 'cliente.historial']);

        //USUARIOS
        // Ver todo lo que hizo un usuario, leido de la bitacora. Suelto, como el
        // resto: se asigna a mano desde Editar Rol.
        Permission::firstOrCreate(['name' => 'user.historial']);
    }
}
