<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los metodos de pago que el negocio acepta (Efectivo, QR, Transferencia,
     * Tarjeta...). Se administran desde su pantalla. Uno con pagos no se borra:
     * se desactiva, y deja de ofrecerse al cobrar (como una sucursal).
     */
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique('nombre', 'metodos_pago_nombre_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
