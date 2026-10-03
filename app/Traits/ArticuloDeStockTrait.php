<?php

namespace App\Traits;

use App\Enums\ArticuloTipo;
use App\Models\StockBaja;
use App\Models\StockSucursal;
use App\Models\StockTransferencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo comun a los dos articulos con stock por cantidad: Repuesto y Accesorio.
 * Comparten stock_sucursales, stock_transferencias y stock_bajas, cada uno por
 * su propia columna (repuesto_id / accesorio_id), que resuelve ArticuloTipo.
 *
 * El modelo que lo usa declara tipoArticulo() y la constante UMBRAL_BAJO_STOCK.
 */
trait ArticuloDeStockTrait
{
    abstract public static function tipoArticulo(): ArticuloTipo;

    /** El stock repartido por sucursal: la unica verdad del inventario. */
    public function stocks()
    {
        return $this->hasMany(StockSucursal::class, static::tipoArticulo()->columna());
    }

    public function transferencias()
    {
        return $this->hasMany(StockTransferencia::class, static::tipoArticulo()->columna());
    }

    public function bajas()
    {
        return $this->hasMany(StockBaja::class, static::tipoArticulo()->columna());
    }

    /**
     * Articulos por debajo del umbral. Mira el TOTAL, no cada sucursal.
     *
     * La columna va CUALIFICADA con la tabla del modelo: este scope se anida
     * como subconsulta dentro de los withSum del resumen por sucursal, y ahi el
     * contexto exterior es stock_sucursales, que TAMBIEN tiene `cantidad`.
     */
    public function scopeBajoStock(Builder $query): Builder
    {
        return $query->where($this->getTable() . '.cantidad', '<=', static::UMBRAL_BAJO_STOCK);
    }

    /** Las unidades que hay en una sucursal concreta. */
    public function stockEn(?int $sucursalId): int
    {
        if ($sucursalId === null) {
            return 0;
        }

        // Si la relacion ya vino cargada no se vuelve a consultar: las tablas y
        // los selectores pintan esto por fila.
        if ($this->relationLoaded('stocks')) {
            return (int) ($this->stocks->firstWhere('sucursal_id', $sucursalId)?->cantidad ?? 0);
        }

        return (int) ($this->stocks()->where('sucursal_id', $sucursalId)->value('cantidad') ?? 0);
    }

    /**
     * El reparto del stock por sucursal, para la columna del catalogo.
     *
     * Clases literales y nada compuesto: no hay safelist en tailwind.config.js.
     * Las sucursales en cero no se pintan, pero los NEGATIVOS si, en rojo:
     * esconderlos seria esconder justo lo que hay que corregir.
     *
     * Espera `stocks.sucursal` ya cargado; si no, son dos consultas por fila.
     */
    public function desgloseStock(): string
    {
        $partes = $this->stocks
            ->filter(fn($s) => (int) $s->cantidad !== 0)
            ->sortByDesc('cantidad')
            ->map(function ($s) {
                $cantidad = (int) $s->cantidad;
                $clase = $cantidad < 0 ? 'text-red-600 font-semibold' : 'font-semibold';

                return '<span class="whitespace-nowrap">' . e($s->sucursal?->nombre ?? 'Sin sucursal')
                    . ' <span class="' . $clase . '">' . $cantidad . '</span></span>';
            });

        if ($partes->isEmpty()) {
            return '<span class="text-gray-400">Sin stock</span>';
        }

        return '<div class="flex flex-col gap-0.5 text-xs">' . $partes->implode('') . '</div>';
    }

    /**
     * Cuantos registros tiene: lineas de compra o venta, transferencias, bajas
     * (y regalos o piezas de reparacion, segun el tipo). Las FK hacia el
     * articulo van en RESTRICT, asi que borrar uno con movimientos fallaria con
     * 1451; esto deja dar el aviso ANTES de intentar.
     */
    public function cantidadMovimientos(): int
    {
        $col = static::tipoArticulo()->columna();
        $id = $this->getKey();
        $db = $this->getConnection();

        $total = $db->table('compras_detalles')->where($col, $id)->count()
            + $db->table('ventas_detalles')->where($col, $id)->count()
            + $db->table('stock_transferencias')->where($col, $id)->count()
            + $db->table('stock_bajas')->where($col, $id)->count();

        if (static::tipoArticulo() === ArticuloTipo::Repuesto) {
            $total += $db->table('productos_reparaciones_repuestos')->where('repuesto_id', $id)->count();
        } else {
            $total += $db->table('productos_regalos')->where('accesorio_id', $id)->count();
        }

        return $total;
    }

    /** "Nombre · SKU" para listas y buscadores. */
    public function etiqueta(): string
    {
        return $this->nombre . ($this->sku ? ' · ' . $this->sku : '');
    }
}
