<?php

use App\Enums\RepuestoTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas_repuestos_detalles', function (Blueprint $table) {
            $table->id();
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('precio', 10, 2);
            $table->integer('cantidad');
            $table->decimal('descuento', 10, 2)->default(0);
            $table->decimal('subtotal_costo', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tipo_cambio', 10, 2)->default(9);
            $table->decimal('subtotal_costo_bs', 10, 2)->default(0);
            $table->decimal('subtotal_bs', 10, 2)->default(0);
            $table->foreignId('repuesto_id')->constrained('repuestos')->restrictOnDelete();
            // Congelado: lo que el articulo era en el momento de la venta.
            $table->enum('tipo', array_map(fn($c) => $c->value, RepuestoTipo::cases()))
                ->default(RepuestoTipo::Repuesto->value);
            $table->foreignId('venta_repuesto_id')->constrained('ventas_repuestos')->cascadeOnDelete();
            // Linea de taller que esta venta cobra. El UNIQUE es quien impide de
            // verdad el doble cobro del mismo repuesto de una reparacion.
            $table->unsignedBigInteger('producto_reparacion_repuesto_id')->nullable();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            $table->unique('producto_reparacion_repuesto_id', 'vrd_reparacion_repuesto_unique');
            $table->foreign('producto_reparacion_repuesto_id', 'vrd_reparacion_repuesto_fk')
                ->references('id')->on('productos_reparaciones_repuestos')->nullOnDelete();
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_repuestos_detalles');
    }
};
