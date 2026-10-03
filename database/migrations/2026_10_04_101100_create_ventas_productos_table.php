<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_productos', function (Blueprint $table) {
            $table->id();
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tipo_cambio', 10, 2);
            $table->decimal('subtotal_bs', 10, 2);
            $table->integer('garantia_meses')->nullable();
            $table->date('garantia_fecha_exp')->nullable();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            // Un telefono no puede estar en dos ventas a la vez. Cancelar BORRA
            // la fila, asi que revender sigue funcionando.
            $table->unique('producto_id', 'ventas_productos_producto_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_productos');
    }
};
