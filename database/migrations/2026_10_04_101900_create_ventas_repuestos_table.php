<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('clave_idempotencia', 36)->nullable();
            $table->integer('cantidad_repuestos')->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            // Suma al total Y al costo, para que se cancele en la ganancia.
            $table->decimal('mano_obra', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('tipo_cambio', 10, 2)->default(9);
            $table->decimal('total_bs', 10, 2)->default(0);
            // total_bs = round(total * tipo_cambio, 2) + ajuste_bs
            $table->decimal('ajuste_bs', 10, 2)->default(0);
            $table->decimal('costo_total_bs', 10, 2)->default(0);
            // Texto congelado: la ficha es lo que se muestra, este texto es el archivo.
            $table->string('cliente')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            // Venta de telefono a la que se enlazan los repuestos cobrados del taller.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'ventas_rep_clave_idem_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_repuestos');
    }
};
