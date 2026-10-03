<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('clave_idempotencia', 36)->nullable();
            $table->date('fecha_compra');
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('tipo_cambio', 10, 2)->default(9);
            $table->integer('cantidad_repuestos')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Sucursal a la que entra el stock.
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('clave_idempotencia', 'compras_rep_clave_idem_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_repuestos');
    }
};
