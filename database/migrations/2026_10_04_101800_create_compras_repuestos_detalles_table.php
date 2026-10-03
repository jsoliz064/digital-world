<?php

use App\Enums\RepuestoTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_repuestos_detalles', function (Blueprint $table) {
            $table->id();
            $table->decimal('costo', 10, 2)->default(0);
            $table->integer('cantidad')->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->foreignId('repuesto_id')->constrained('repuestos')->restrictOnDelete();
            // Congelado: lo que el articulo era al comprarlo, para que
            // reclasificarlo no reescriba un periodo cerrado.
            $table->enum('tipo', array_map(fn($c) => $c->value, RepuestoTipo::cases()))
                ->default(RepuestoTipo::Repuesto->value);
            $table->foreignId('compra_repuesto_id')->constrained('compras_repuestos')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_repuestos_detalles');
    }
};
