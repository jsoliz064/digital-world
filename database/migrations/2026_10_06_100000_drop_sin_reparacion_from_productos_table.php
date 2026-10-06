<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuera el DOA (`sin_reparacion`): la casilla ya no se usaba, y lo unico que
     * la leia era la seccion «Para Reparacion» del catalogo, que tambien se fue.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('sin_reparacion');
        });
    }

    /** Vuelve la columna, todo en false: los valores borrados no se recuperan. */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('sin_reparacion')->default(false)->after('garantia_activa');
        });
    }
};
