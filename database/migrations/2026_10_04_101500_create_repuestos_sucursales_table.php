<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La unica verdad del stock de repuestos. `repuestos.cantidad` es solo su
     * suma cacheada. `cantidad` lleva signo a proposito: asi un descuadre se
     * ve en vez de reventar la consulta.
     */
    public function up(): void
    {
        Schema::create('repuestos_sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repuesto_id')->constrained('repuestos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->restrictOnDelete();
            $table->integer('cantidad')->default(0);
            $table->timestamps();

            $table->unique(['repuesto_id', 'sucursal_id'], 'repuestos_sucursales_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos_sucursales');
    }
};
