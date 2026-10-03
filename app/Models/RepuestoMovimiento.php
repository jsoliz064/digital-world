<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * ############################################################################
 * # MODELO VIRTUAL: LA TABLA `repuesto_movimientos` NO EXISTE EN LA BASE DE  #
 * # DATOS. No busques su migración, no la hay.                              #
 * ############################################################################
 *
 * En este sistema NO hay kardex de repuestos: el stock se escribe desde
 * StockRepuestoService, que es el unico camino, pero no deja una fila de
 * movimiento propia. Esta clase es SOLO un envoltorio Eloquent sobre el
 * UNION ALL de las CUATRO fuentes reales, repartidas en CINCO ramas:
 *
 *   compras_repuestos_detalles        -> Entrada
 *   ventas_repuestos_detalles         -> Salida
 *   productos_reparaciones_repuestos  -> Salida
 *   repuestos_transferencias          -> Salida en el origen  (rama 4)
 *   repuestos_transferencias          -> Entrada en el destino (rama 5)
 *
 * La transferencia va en DOS ramas porque es NETA CERO: ver el comentario de
 * la rama 4, mas abajo.
 *
 * Es de SOLO LECTURA: los hooks saving/deleting lanzan excepción a propósito.
 * Nunca lo consultes fuera de paraRepuesto(): sin el fromSub() Eloquent
 * intentará leer una tabla inexistente.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ $table Y EL ALIAS DEL fromSub SON EL MISMO STRING (no lo cambies):
 * rappasoft/laravel-livewire-tables construye cada SELECT como
 * "{$column->getTable()}.{$column->getField()} as {$field}"
 * (Views/Columns/Traits/Helpers/ColumnHelpers.php::getColumn), y getTable()
 * lo rellena Traits/Helpers/ColumnHelpers.php::setupColumns() con
 * $this->getBuilder()->getModel()->getTable(). Si el alias del fromSub no es
 * exactamente $table, todas las columnas apuntan a una tabla que no está en
 * el FROM y la query revienta.
 *
 * POR QUÉ $incrementing = false (esto NO es cosmético):
 * el id sintético es 'C-1' / 'V-18' / 'R-14'. HasAttributes::getCasts() añade
 * [keyName => keyType] a los casts SOLO si getIncrementing() es true; con el
 * default (true + 'int') Eloquent castea 'C-1' a 0 en TODAS las filas, todos
 * los wire:key de la tabla colisionan y Livewire recicla el DOM mal.
 * ---------------------------------------------------------------------------
 */
class RepuestoMovimiento extends Model
{
    /** Alias del fromSub. Tabla VIRTUAL: no existe en la BD. */
    protected $table = 'repuesto_movimientos';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Sin casts decimal:* a propósito: los importes se formatean con
     * number_format((float) $value, 2) en las columnas, y así los NULL de
     * `precio` / `total_venta` (compras y reparaciones) llegan como NULL
     * limpios a los format callbacks.
     */
    protected $casts = [
        'fecha' => 'datetime',
        'referencia_id' => 'integer',
        'cantidad' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function () {
            throw new LogicException('RepuestoMovimiento es de solo lectura: `repuesto_movimientos` es una tabla virtual (UNION), no existe en la BD.');
        });

        static::deleting(function () {
            throw new LogicException('RepuestoMovimiento es de solo lectura: `repuesto_movimientos` es una tabla virtual (UNION), no existe en la BD.');
        });
    }

    /**
     * UNION ALL de las cinco ramas, envuelto en un builder Eloquent.
     *
     * ¡OJO! Las ramas de un UNION se emparejan por POSICIÓN, no por nombre, y
     * los nombres de columna resultantes son los de la PRIMERA rama. Son TRECE
     * columnas: si añades o quitas una, hazlo en las CINCO y en el MISMO ORDEN.
     *
     * Sin orderBy aquí: applySorting() de rappasoft ACUMULA orderBy en vez de
     * reemplazarlos, así que un orden fijo aquí dejaría el del usuario como
     * criterio secundario. El orden va en setDefaultSort().
     */
    public static function paraRepuesto(int $repuestoId): Builder
    {
        // 1) COMPRAS -> ENTRADA
        // TIMESTAMP(fecha_compra, TIME(created_at)): fecha_compra es DATE (NOT NULL)
        // y a pelo daría 00:00:00, con lo que toda compra aparecería antes que las
        // ventas del mismo día. Conservamos la fecha de negocio con la hora real
        // de registro.
        $compras = DB::table('compras_repuestos_detalles as d')
            ->join('compras_repuestos as c', 'd.compra_repuesto_id', '=', 'c.id')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 'c.sucursal_id', '=', 's.id')
            ->where('d.repuesto_id', $repuestoId)
            ->selectRaw("
                CONCAT('C-', d.id)                            as id,
                'Compra'                                      as tipo,
                'Entrada'                                     as direccion,
                c.id                                          as referencia_id,
                TIMESTAMP(c.fecha_compra, TIME(d.created_at)) as fecha,
                'Compra de stock'                             as detalle,
                d.cantidad                                    as cantidad,
                d.costo                                       as costo,
                NULL                                          as precio,
                d.subtotal                                    as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 2) VENTAS -> SALIDA
        // ventas_repuestos no tiene columna de fecha: la fecha es created_at del detalle.
        $ventas = DB::table('ventas_repuestos_detalles as d')
            ->join('ventas_repuestos as v', 'd.venta_repuesto_id', '=', 'v.id')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            // Por d.sucursal_id y NO por v.sucursal_id: el dato de stock es el
            // de la LINEA. Una devolucion vuelve a la sucursal de la linea, no
            // a la de la cabecera, y el historial tiene que decir lo mismo que
            // hace el stock.
            ->leftJoin('sucursales as s', 'd.sucursal_id', '=', 's.id')
            // La ficha del cliente: el nombre VIGENTE manda sobre el texto
            // congelado de la venta, que queda como respaldo de las ordenes
            // anteriores al modulo de clientes.
            ->leftJoin('clientes as cl', 'v.cliente_id', '=', 'cl.id')
            ->where('d.repuesto_id', $repuestoId)
            ->selectRaw("
                CONCAT('V-', d.id)                            as id,
                'Venta'                                       as tipo,
                'Salida'                                      as direccion,
                v.id                                          as referencia_id,
                d.created_at                                  as fecha,
                -- La sucursal sale de aqui: ahora es columna propia y la pintaria
                -- dos veces en la misma fila.
                CONCAT(
                    COALESCE(cl.nombre, v.cliente, 'Sin cliente'),
                    CASE WHEN d.producto_reparacion_repuesto_id IS NULL THEN ''
                         ELSE ' · cobrado con la venta del equipo (ya descontado en la reparación)' END
                )                                             as detalle,
                -- Un repuesto cobrado junto al telefono salio del almacen en la
                -- reparacion, no aqui. Se muestra el dinero pero NO el
                -- movimiento, o el pie de la tabla contaria la salida dos veces
                -- y dejaria de cuadrar con el stock real.
                CASE WHEN d.producto_reparacion_repuesto_id IS NULL THEN d.cantidad ELSE 0 END
                                                              as cantidad,
                d.costo                                       as costo,
                d.precio                                      as precio,
                d.subtotal_costo                              as subtotal_costo,
                d.subtotal                                    as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 3) REPARACIONES -> SALIDA
        // productos_reparaciones no tiene user_id: `usuario` va NULL a propósito.
        // Tampoco hay precio de venta del repuesto: solo costo.
        $reparaciones = DB::table('productos_reparaciones_repuestos as rr')
            ->join('productos_reparaciones as r', 'rr.producto_reparacion_id', '=', 'r.id')
            ->join('productos as p', 'r.producto_id', '=', 'p.id')
            ->leftJoin('tecnicos as t', 'r.tecnico_id', '=', 't.id')
            // La sucursal CONGELADA de la linea: de ahi salio la pieza, aunque
            // el equipo se haya mudado al Almacen al terminar la reparacion.
            ->leftJoin('sucursales as s', 'rr.sucursal_id', '=', 's.id')
            ->where('rr.repuesto_id', $repuestoId)
            ->selectRaw("
                CONCAT('R-', rr.id)                           as id,
                'Reparacion'                                  as tipo,
                'Salida'                                      as direccion,
                r.id                                          as referencia_id,
                rr.created_at                                 as fecha,
                CONCAT('IMEI ', p.imei, COALESCE(CONCAT(' - ', t.nombre), ''))
                                                              as detalle,
                rr.cantidad                                   as cantidad,
                rr.costo                                      as costo,
                NULL                                          as precio,
                rr.subtotal_costo                             as subtotal_costo,
                NULL                                          as total_venta,
                NULL                                          as usuario,
                s.nombre                                      as sucursal
            ");

        // 4) TRANSFERENCIA -> SALIDA en el origen
        //
        // Dos ramas y no una porque una transferencia es NETA CERO. El saldo de
        // RepuestoHistorialIndex es un SUM(CASE WHEN direccion='Entrada' THEN
        // cantidad ELSE -cantidad END) sobre todo el UNION: con una sola fila
        // dejaria de cuadrar con el total y el banner de "el balance calculado
        // no coincide" se encenderia POR DISENO en cada articulo transferido.
        // Ese banner es el unico detector de deriva que hay; convertirlo en
        // ruido lo inutiliza.
        //
        // El sufijo -S / -E del id NO es cosmetico: sin el las dos filas
        // comparten wire:key y Livewire recicla el DOM mal. Mismo motivo que
        // $incrementing = false.
        //
        // Sin importes: una transferencia no mueve dinero. Los COALESCE(SUM())
        // del footer absorben los NULL.
        $transferenciaSalida = DB::table('repuestos_transferencias as t')
            ->leftJoin('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 't.sucursal_origen_id', '=', 's.id')
            ->leftJoin('sucursales as sd', 't.sucursal_destino_id', '=', 'sd.id')
            ->where('t.repuesto_id', $repuestoId)
            ->selectRaw("
                CONCAT('T-', t.id, '-S')                      as id,
                'Transferencia'                               as tipo,
                'Salida'                                      as direccion,
                t.id                                          as referencia_id,
                t.created_at                                  as fecha,
                CONCAT('Transferencia hacia ', COALESCE(sd.nombre, 'sin sucursal'))
                                                              as detalle,
                t.cantidad                                    as cantidad,
                NULL                                          as costo,
                NULL                                          as precio,
                NULL                                          as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 5) TRANSFERENCIA -> ENTRADA en el destino. La misma fila leida al
        // reves: misma cantidad, direccion opuesta, y la sucursal es el destino.
        $transferenciaEntrada = DB::table('repuestos_transferencias as t')
            ->leftJoin('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 't.sucursal_destino_id', '=', 's.id')
            ->leftJoin('sucursales as so', 't.sucursal_origen_id', '=', 'so.id')
            ->where('t.repuesto_id', $repuestoId)
            ->selectRaw("
                CONCAT('T-', t.id, '-E')                      as id,
                'Transferencia'                               as tipo,
                'Entrada'                                     as direccion,
                t.id                                          as referencia_id,
                t.created_at                                  as fecha,
                CONCAT('Transferencia desde ', COALESCE(so.nombre, 'sin sucursal'))
                                                              as detalle,
                t.cantidad                                    as cantidad,
                NULL                                          as costo,
                NULL                                          as precio,
                NULL                                          as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        $union = $compras->unionAll($ventas)->unionAll($reparaciones)
            ->unionAll($transferenciaSalida)->unionAll($transferenciaEntrada);

        // El alias DEBE ser 'repuesto_movimientos' (= $table). Ver docblock.
        return static::query()->fromSub($union, 'repuesto_movimientos');
    }
}
