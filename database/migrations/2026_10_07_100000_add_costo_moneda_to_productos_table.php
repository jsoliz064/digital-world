<?php

use App\Enums\Moneda;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El costo de un equipo puede cargarse en USD con su tipo de cambio. El
     * costo en Bs sigue en `costo_unidad` (lo deriva Producto al guardar) y es
     * lo unico que leen la compra, la venta y los reportes: estas columnas solo
     * dicen de donde salio ese numero. Los equipos existentes quedan en BOB.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->enum('costo_moneda', Moneda::values())->default(Moneda::BOB->value)->after('costo_unidad');
            $table->decimal('costo_moneda_monto', 10, 2)->nullable()->after('costo_moneda');
            $table->decimal('costo_tipo_cambio', 10, 4)->nullable()->after('costo_moneda_monto');
        });

        // En Bs, sin dolares ni tipo de cambio; en USD, los dos. El IS NOT NULL
        // del tipo de cambio no sobra: con NULL, `> 0` da desconocido y un CHECK
        // solo rechaza lo FALSO, asi que un USD sin tipo de cambio pasaba.
        DB::statement("ALTER TABLE productos ADD CONSTRAINT productos_costo_moneda CHECK (
            (costo_moneda = 'BOB' AND costo_moneda_monto IS NULL AND costo_tipo_cambio IS NULL)
            OR (costo_moneda = 'USD' AND costo_moneda_monto IS NOT NULL
                AND costo_tipo_cambio IS NOT NULL AND costo_tipo_cambio > 0))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos DROP CHECK productos_costo_moneda');

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['costo_moneda', 'costo_moneda_monto', 'costo_tipo_cambio']);
        });
    }
};
