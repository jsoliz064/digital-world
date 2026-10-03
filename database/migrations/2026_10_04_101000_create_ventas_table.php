<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola cabecera para todo lo que se vende: equipos, repuestos y
     * accesorios, en Bs. Lo vendido vive en ventas_detalles.
     *
     * Antes eran dos documentos (ventas y ventas_repuestos), y cobrar los
     * repuestos de una reparacion junto con el telefono creaba una segunda
     * venta enlazada por venta_id. Ahora todo es una venta, una nota y un
     * cobro (etapa 5: pago multiple, permuta).
     *
     * El `id` es el numero correlativo de la nota.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('clave_idempotencia', 36)->nullable();
            // Cacheados desde las lineas por Venta::recalcularTotales():
            //   subtotal    = SUM(lineas.subtotal)
            //   total       = subtotal - descuento + mano_obra
            //   costo_total = SUM(lineas.subtotal_costo) + mano_obra
            // mano_obra suma al total Y al costo para cancelarse en la ganancia.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('mano_obra', 10, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('costo_total', 12, 2)->default(0);
            // Texto congelado de lo que se escribio al vender: la ficha es lo
            // que se muestra (Venta::nombreCliente()), este texto es el archivo.
            $table->string('cliente')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'ventas_clave_idem_unico');
            $table->index('created_at', 'ventas_fecha_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
