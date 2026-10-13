<?php

namespace App\Traits;

/**
 * Normaliza el SKU y el UPC de un articulo: sin espacios y NULL cuando vienen
 * vacios.
 *
 * Nacio por el UNIQUE de `sku` (dos '' chocaban con 1062). El SKU ya no es
 * unico (2026_10_13_100000_sku_no_unico), pero vacio sigue siendo NULL: asi un
 * codigo en blanco nunca coincide en los buscadores, que comparan sku = ?.
 */
trait NormalizaCodigosTrait
{
    public function setSkuAttribute($value): void
    {
        $this->attributes['sku'] = self::normalizarCodigo($value);
    }

    public function setUpcAttribute($value): void
    {
        $this->attributes['upc'] = self::normalizarCodigo($value);
    }

    public static function normalizarCodigo($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
