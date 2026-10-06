<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuera el color del tecnico: el boton de estado de los equipos en
     * reparacion vuelve al color del estado, y el tecnico se nombra.
     */
    public function up(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }

    /** Vuelve la columna vacia: los colores borrados no se recuperan. */
    public function down(): void
    {
        Schema::table('tecnicos', function (Blueprint $table) {
            $table->string('color', 10)->nullable()->after('nombre');
        });
    }
};
