<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            // Idempotencia: reintentar un guardado no crea una segunda compra.
            $table->string('clave_idempotencia', 36)->nullable();
            $table->date('fecha_compra');
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->integer('cantidad_total')->default(0);
            $table->decimal('tipo_cambio', 10, 2);
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'compras_clave_idem_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
