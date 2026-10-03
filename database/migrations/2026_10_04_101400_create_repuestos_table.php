<?php

use App\Enums\RepuestoTipo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // Repuesto (pieza de reparacion) o Accesorio: dos pantallas de la
            // misma tabla.
            $table->enum('tipo', array_map(fn($c) => $c->value, RepuestoTipo::cases()))
                ->default(RepuestoTipo::Repuesto->value);
            $table->string('upc', 50)->nullable();
            $table->string('fabricante')->nullable();
            $table->string('color', 50)->nullable();
            $table->string('color_hex', 10)->nullable();
            $table->decimal('costo', 10, 2)->default(0);
            $table->decimal('tipo_cambio', 10, 2)->default(9);
            $table->decimal('precio', 10, 2)->default(0);
            // Total cacheado: la suma de repuestos_sucursales. Solo lo escribe
            // StockRepuestoService::recalcularTotales().
            $table->integer('cantidad')->default(0);
            $table->foreignId('producto_modelo_id')->nullable()->constrained('productos_modelos')->nullOnDelete();
            $table->foreignId('repuesto_categoria_id')->nullable()->constrained('repuestos_categorias')->nullOnDelete();
            $table->timestamps();

            $table->index('tipo');
            $table->index('upc', 'repuestos_upc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repuestos');
    }
};
