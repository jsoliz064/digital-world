<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role1 = Role::firstOrCreate(['name' => 'Administrador']);
        $role2 = Role::firstOrCreate(['name' => 'Vendedor']);
        $role3 = Role::firstOrCreate(['name' => 'Solo tecnicos']);

        Permission::firstOrCreate(['name' => 'dashboard.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'reporte.index'])->syncRoles([$role1]);

        //ROLES
        Permission::firstOrCreate(['name' => 'rol.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'rol.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'rol.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'rol.delete'])->syncRoles([$role1]);
        //USUARIOS
        Permission::firstOrCreate(['name' => 'user.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'user.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'user.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'user.delete'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'user.desactivar'])->syncRoles([$role1]);

        Permission::firstOrCreate(['name' => 'sucursal.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'sucursal.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'sucursal.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'sucursal.delete'])->syncRoles([$role1]);
        //PROVEEDORES
        Permission::firstOrCreate(['name' => 'proveedor.index'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'proveedor.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'proveedor.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'proveedor.delete'])->syncRoles([$role1]);
        //TECNICOS
        Permission::firstOrCreate(['name' => 'tecnico.index'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'tecnico.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'tecnico.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'tecnico.delete'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'tecnico.productos'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'tecnico.productos.exportar'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'tecnico.productos.pagos'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'tecnico.productos.terminar'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'tecnico.producto.edit'])->syncRoles([$role1, $role2]);
        //PRODUCTOS MARCAS
        Permission::firstOrCreate(['name' => 'producto-marca.index'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto-marca.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-marca.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-marca.delete'])->syncRoles([$role1]);
        //PRODUCTOS CATEGORIAS
        Permission::firstOrCreate(['name' => 'producto-categoria.index'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto-categoria.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-categoria.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-categoria.delete'])->syncRoles([$role1]);
        //PRODUCTOS MODELOS
        Permission::firstOrCreate(['name' => 'producto-modelo.index'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto-modelo.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-modelo.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-modelo.delete'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto-modelo.almacenamiento'])->syncRoles([$role1]);
        //PRODUCTOS
        Permission::firstOrCreate(['name' => 'producto.index'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'producto.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto.delete'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'producto.historial'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'producto.historial.show'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'producto.garantia'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto.cambiar-sucursal'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto.reporte'])->syncRoles([$role1]);

        //REPUESTOS
        Permission::firstOrCreate(['name' => 'repuesto.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'repuesto.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'repuesto.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'repuesto.delete'])->syncRoles([$role1]);

        //COMPRAS PRODUCTOS
        Permission::firstOrCreate(['name' => 'compra.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.productos'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.delete'])->syncRoles([$role1]);

        //COMPRAS REPUESTOS
        Permission::firstOrCreate(['name' => 'compra.repuesto.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.repuesto.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.repuesto.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'compra.repuesto.delete'])->syncRoles([$role1]);
        //VENTAS
        Permission::firstOrCreate(['name' => 'venta.reporte'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'venta.index'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'venta.create'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'venta.detalle'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'venta.detalle.delete'])->syncRoles([$role1, $role2]);

        Permission::firstOrCreate(['name' => 'venta.repuesto.reporte'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'venta.repuesto.index'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'venta.repuesto.create'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'venta.repuesto.edit'])->syncRoles([$role1]);
        Permission::firstOrCreate(['name' => 'venta.repuesto.delete'])->syncRoles([$role1]);


        Permission::firstOrCreate(['name' => 'producto.estado.inventario'])->syncRoles([$role1, $role2, $role3]);
        // Oferta es Inventario con un cartel: mismos roles que el.
        Permission::firstOrCreate(['name' => 'producto.estado.oferta'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'producto.estado.vendido'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto.estado.reparacion'])->syncRoles([$role1, $role2, $role3]);
        Permission::firstOrCreate(['name' => 'producto.estado.fuera'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto.estado.roto'])->syncRoles([$role1, $role2]);
        Permission::firstOrCreate(['name' => 'producto.estado.transito'])->syncRoles([$role1, $role2]);
    }
}
