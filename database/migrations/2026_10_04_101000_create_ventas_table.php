<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('clave_idempotencia', 36)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->decimal('tipo_cambio', 10, 2);
            $table->decimal('total_bs', 10, 2);
            // Texto congelado de lo que se escribio al vender: la ficha es lo
            // que se muestra (Venta::nombreCliente()), este texto es el archivo.
            $table->string('cliente')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'ventas_clave_idem_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
