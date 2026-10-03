<?php

namespace App\Console\Commands;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Detector de deriva del inventario. SOLO LECTURA.
 *
 * Existe por lo mismo que el banner de "el balance calculado no coincide" del
 * historial de repuestos: la deriva se mide, no se asume. Cuando se reporto que
 * un telefono quedaba "Vendido sin orden de venta", la unica forma de saber si
 * seguia pasando fue escribir estas seis consultas a mano; vivir en un comando
 * las vuelve repetibles.
 *
 * Sigue siendo util DESPUES de los indices unicos de productos.imei y
 * ventas_productos.producto_id: esos cierran la puerta de la aplicacion, pero
 * nada impide un UPDATE a mano en la base -- que es a lo que se parecen los
 * tres productos que aparecieron en Oferta con su ultimo historial en
 * Inventario.
 *
 * No arregla nada a proposito. Cada hallazgo necesita una decision de negocio
 * (?esa venta se cobro?), y un --fix que adivine seria peor que el problema.
 */
class AuditarProductos extends Command
{
    protected $signature = 'productos:auditar';

    protected $description = 'Busca inconsistencias entre productos, ventas, historial y stock (solo lectura)';

    /** Hallazgos acumulados, para decidir el codigo de salida al final. */
    private int $problemas = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Auditoria de inventario</> — solo lectura');

        $this->vendidosSinVenta();
        $this->enVentaSinEstarVendidos();
        $this->ventasSinDetalles();
        $this->productosEnDosVentas();
        $this->imeiDuplicados();
        $this->historialDesalineado();
        $this->stockDescuadrado();

        $this->newLine();

        if ($this->problemas === 0) {
            $this->info('Sin inconsistencias.');

            return self::SUCCESS;
        }

        $this->error("{$this->problemas} comprobacion(es) con hallazgos.");

        // Codigo != 0 para que sirva en un cron o en un hook sin leer la salida.
        return self::FAILURE;
    }

    /**
     * El sintoma que origino todo: el telefono marcado como vendido y la orden
     * de venta en ninguna parte.
     */
    private function vendidosSinVenta(): void
    {
        $filas = DB::table('productos as p')
            ->where('p.estado', ProductoEstado::Vendido->value)
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('ventas_productos as vp')
                ->whereColumn('vp.producto_id', 'p.id'))
            ->select('p.id', 'p.imei', 'p.updated_at')
            ->orderByDesc('p.updated_at')
            ->get();

        $this->reportar('Productos Vendido sin linea de venta', $filas, ['id', 'imei', 'updated_at']);
    }

    /**
     * El reverso: la venta existe pero el telefono sigue disponible, asi que
     * puede volver a venderse.
     */
    private function enVentaSinEstarVendidos(): void
    {
        $filas = DB::table('ventas_productos as vp')
            ->join('productos as p', 'p.id', '=', 'vp.producto_id')
            ->where('p.estado', '<>', ProductoEstado::Vendido->value)
            ->select('p.id', 'p.imei', 'p.estado', 'vp.venta_id')
            ->get();

        $this->reportar('Productos en una venta sin estar Vendido', $filas, ['id', 'imei', 'estado', 'venta_id']);
    }

    /**
     * Una cabecera sin lineas es exactamente lo que un usuario describe como
     * "la venta no se guardo". Las deja VentaDetalleDestroyModal al borrar el
     * ultimo detalle.
     */
    private function ventasSinDetalles(): void
    {
        $filas = DB::table('ventas as v')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('ventas_productos as vp')
                ->whereColumn('vp.venta_id', 'v.id'))
            ->select('v.id', 'v.cliente', 'v.total', 'v.created_at')
            ->orderByDesc('v.id')
            ->get();

        $this->reportar('Ventas sin ninguna linea', $filas, ['id', 'cliente', 'total', 'created_at']);
    }

    /**
     * Lo que impide el indice unico de ventas_productos.producto_id. Con
     * Producto::ventaProducto() siendo hasOne, la segunda venta es invisible
     * desde la ficha del telefono.
     */
    private function productosEnDosVentas(): void
    {
        $filas = DB::table('ventas_productos')
            ->selectRaw('producto_id, COUNT(*) as veces, GROUP_CONCAT(venta_id) as ventas')
            ->groupBy('producto_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->reportar('El mismo producto en dos o mas ventas', $filas, ['producto_id', 'veces', 'ventas']);
    }

    /** Lo que impide el indice unico de productos.imei. */
    private function imeiDuplicados(): void
    {
        $filas = DB::table('productos')
            ->selectRaw('imei, COUNT(*) as veces, GROUP_CONCAT(id) as ids')
            ->groupBy('imei')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->reportar('IMEI duplicados', $filas, ['imei', 'veces', 'ids']);
    }

    /**
     * CLAUDE.md promete que el historial es "la unica traza del telefono". Esta
     * consulta mide cuanto se cumple: compara el estado real con el de la
     * ultima fila escrita.
     *
     * Antes de EstadoProductoService daba 6 de 93, porque TecnicoTerminarModal
     * y ReparacionEditModal escribian 'Reparacion' en el historial mientras
     * movian el producto a 'Inventario', y dos caminos de compra cambiaban el
     * estado sin dejar fila. Esas filas viejas NO se reescriben -- son
     * historia-- asi que este numero no baja a cero con el arreglo; lo que
     * importa es que no suba.
     *
     * Lee la BITACORA, y no cualquier fila: solo las que llevan un estado como
     * evento. Las demas -- 'editado' del observer, 'garantia', 'cobro' -- no
     * dicen en que estado quedo el telefono y compararlas daria falsos
     * positivos. Un cambio de estado que se saltara EstadoProductoService se
     * sigue cazando: deja la ultima fila CON estado diciendo el anterior.
     */
    private function historialDesalineado(): void
    {
        $estados = array_column(ProductoEstado::cases(), 'value');
        $huecos = implode(',', array_fill(0, count($estados), '?'));

        $filas = DB::select(
            "SELECT p.id, p.imei, p.estado AS estado_real, b.evento AS estado_historial, b.created_at
               FROM productos p
               JOIN bitacoras b
                 ON b.id = (SELECT b2.id
                              FROM bitacoras b2
                             WHERE b2.auditable_type = ?
                               AND b2.auditable_id = p.id
                               AND b2.evento IN ($huecos)
                             ORDER BY b2.id DESC
                             LIMIT 1)
              WHERE b.evento <> p.estado",
            [(new Producto)->getMorphClass(), ...$estados],
        );

        $this->reportar(
            'Ultima fila de historial distinta del estado real',
            collect($filas),
            ['id', 'imei', 'estado_real', 'estado_historial', 'created_at'],
        );
    }

    /**
     * La invariante del modulo de repuestos: repuestos.cantidad es un total
     * cacheado de repuestos_sucursales. recalcularTotales() es auto-sanante, asi
     * que una fila aqui significa que algo escribio el total por fuera del
     * servicio.
     */
    private function stockDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT r.id, r.nombre, r.cantidad AS total_cacheado,
                    COALESCE(SUM(rs.cantidad), 0) AS suma_sucursales
               FROM repuestos r
          LEFT JOIN repuestos_sucursales rs ON rs.repuesto_id = r.id
           GROUP BY r.id, r.nombre, r.cantidad
             HAVING r.cantidad <> COALESCE(SUM(rs.cantidad), 0)'
        );

        $this->reportar(
            'repuestos.cantidad distinto de la suma por sucursal',
            collect($filas),
            ['id', 'nombre', 'total_cacheado', 'suma_sucursales'],
        );
    }

    /** Imprime una comprobacion y cuenta el hallazgo si trajo filas. */
    private function reportar(string $titulo, $filas, array $columnas): void
    {
        $this->newLine();

        if ($filas->isEmpty()) {
            $this->line("  <fg=green>ok</>    {$titulo}");

            return;
        }

        $this->problemas++;
        $this->line("  <fg=red>FALLA</> {$titulo}: <options=bold>{$filas->count()}</>");

        $this->table(
            $columnas,
            // Solo las primeras 20: el objetivo es avisar, no volcar la base.
            $filas->take(20)->map(fn($fila) => array_map(
                fn($columna) => (string) (((array) $fila)[$columna] ?? ''),
                array_combine($columnas, $columnas),
            ))->all(),
        );

        if ($filas->count() > 20) {
            $this->line('  ... y ' . ($filas->count() - 20) . ' mas');
        }
    }
}
