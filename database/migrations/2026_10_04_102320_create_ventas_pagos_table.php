<?php

use App\Enums\PagoMomento;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que se cobro de cada venta: al venderla (momento Venta) y en los cobros
     * posteriores de una venta a credito (momento Cobro). Lo escribe SOLO
     * PagoService, que tambien cachea la suma en ventas.pagado.
     *
     * Se llama "pago" y no "cobro" porque "cobro" ya es, en el codigo, el cobro
     * de las piezas de una reparacion (una linea de venta).
     *
     * Anular un pago borra la fila: la historia queda en la bitacora de la
     * venta (evento pago-anulado), como todo lo que se deshace.
     */
    public function up(): void
    {
        Schema::create('ventas_pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->unsignedBigInteger('metodo_pago_id');
            $table->decimal('monto', 12, 2);
            $table->enum('momento', PagoMomento::values());
            $table->dateTime('fecha');
            $table->string('nota')->nullable();
            // Quien recibio el dinero.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Un cobro reparte la MISMA clave entre las ventas que salda, y una
            // venta cobrada con dos metodos lleva la clave en los dos pagos: el
            // reintento choca aqui. Por eso la unicidad es (clave, venta, metodo)
            // y PagoService junta en uno los pagos del mismo metodo.
            $table->string('clave_idempotencia', 36)->nullable();
            $table->timestamps();

            // RESTRICT: anular una venta borra antes sus pagos (AnulacionVentaService),
            // nunca por cascada.
            $table->foreign('venta_id', 'vp_venta_fk')->references('id')->on('ventas')->restrictOnDelete();
            $table->foreign('metodo_pago_id', 'vp_metodo_fk')->references('id')->on('metodos_pago')->restrictOnDelete();

            $table->unique(['clave_idempotencia', 'venta_id', 'metodo_pago_id'], 'vp_clave_idem_venta_metodo_unico');
            $table->index('fecha', 'vp_fecha_idx');
        });

        DB::statement('ALTER TABLE ventas_pagos ADD CONSTRAINT vp_monto_positivo CHECK (monto > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas_pagos');
    }
};
