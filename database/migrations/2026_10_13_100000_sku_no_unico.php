<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El SKU deja de ser unico en productos, accesorios y repuestos: es un texto
 * libre ("FUNDA-SILICONA") que puede repetirse, no un codigo de barras. Queda un
 * indice comun, como el del UPC, porque los buscadores filtran por sku = ?.
 */
return new class extends Migration
{
    private const TABLAS = ['productos', 'accesorios', 'repuestos'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropUnique("{$tabla}_sku_unico");
                $table->index('sku', "{$tabla}_sku_index");
            });
        }
    }

    /** Falla si ya hay SKU repetidos: no se vuelve atras sin limpiar los datos. */
    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropIndex("{$tabla}_sku_index");
                $table->unique('sku', "{$tabla}_sku_unico");
            });
        }
    }
};
