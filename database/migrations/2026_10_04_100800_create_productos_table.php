<?php

use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Enums\ProductoVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 20);
            $table->string('upc', 50)->nullable();
            $table->string('almacenamiento');
            $table->enum('version', array_map(fn($c) => $c->value, ProductoVersion::cases()))->nullable();
            $table->enum('color', array_map(fn($c) => $c->value, ProductoColor::cases()));
            $table->integer('bateria_porcentaje');
            $table->string('descripcion')->nullable();
            $table->text('detalles')->nullable();
            $table->string('estado_grado')->nullable();
            $table->enum('estado', array_map(fn($c) => $c->value, ProductoEstado::cases()))
                ->default(ProductoEstado::Inventario->value);

            $table->decimal('costo_unidad', 10, 2)->default(0);
            $table->decimal('costo_envio', 10, 2)->default(0);
            $table->decimal('costo_reparacion', 10, 2)->default(0);
            $table->decimal('costo_total', 10, 2)->default(0);
            $table->decimal('precio_cliente', 10, 2)->default(0);
            $table->decimal('precio_cliente_ant', 10, 2)->default(0);
            $table->decimal('precio_vendedor', 10, 2)->default(0);
            $table->decimal('precio_vendedor_ant', 10, 2)->default(0);

            $table->boolean('venta_rapida')->default(false);
            $table->boolean('disponible_catalogo')->default(true);
            $table->boolean('garantia_activa')->default(false);
            $table->boolean('sin_reparacion')->default(false);

            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('producto_modelo_id')->constrained('productos_modelos')->restrictOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->timestamps();

            // La garantia de verdad contra el IMEI repetido: la regla unique:
            // valida con un SELECT previo y dos pestanas la pasan las dos.
            $table->unique('imei', 'productos_imei_unico');
            $table->index('upc', 'productos_upc_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
