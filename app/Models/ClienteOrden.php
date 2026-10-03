<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * ############################################################################
 * # MODELO VIRTUAL: LA TABLA `cliente_ordenes` NO EXISTE EN LA BASE DE DATOS. #
 * # No busques su migracion, no la hay.                                      #
 * ############################################################################
 *
 * Las ordenes de un cliente, vengan de donde vengan. Es un envoltorio Eloquent
 * sobre el UNION ALL de las DOS fuentes reales:
 *
 *   ventas            -> una venta de telefonos
 *   ventas_repuestos  -> una venta de repuestos o accesorios SUELTA
 *
 * Hermano de RepuestoMovimiento y con las mismas reglas; su docblock las explica
 * todas y aqui solo se repiten las que esta clase tuvo que resolver.
 *
 * ---------------------------------------------------------------------------
 * POR QUE LAS VENTAS DE REPUESTOS ENLAZADAS NO SON UNA FILA (whereNull venta_id)
 *
 * Cobrar los repuestos montados en un telefono crea una VentaRepuesto con
 * `venta_id`, y RepuestosDeReparacionService le copia el cliente de la venta del
 * equipo. Si entrara como fila propia, el cliente veria DOS ordenes donde hizo
 * UNA compra, y el duplicado seria sistematico, no excepcional.
 *
 * El criterio ya lo fijo ReporteIndex y esta escrito ahi: "las enlazadas a una
 * venta de telefono se excluyen: no son una operacion aparte, son la misma venta
 * al mismo cliente, ya contada en las de celulares. Sin esto el ticket promedio
 * baja solo."
 *
 * Su DINERO si cuenta, y no es una contradiccion: el importe de los repuestos va
 * POR ENCIMA del total del telefono -- se cobra aparte y con costo cero -- asi
 * que la rama de ventas lo suma en `total_repuestos` y lo muestra dentro de la
 * fila del telefono. Una orden, dos cifras.
 *
 * POR QUE $incrementing = false
 * El id es sintetico ('VP-15', 'VR-12'). Con el default (true + 'int') Eloquent
 * castea 'VP-15' a 0 en TODAS las filas, los wire:key colisionan y Livewire
 * recicla el DOM mal.
 *
 * POR QUE $table Y EL ALIAS DEL fromSub SON EL MISMO STRING
 * rappasoft compone cada SELECT como "{$column->getTable()}.{$field}" y saca
 * getTable() del modelo: si el alias no coincide, las columnas apuntan a una
 * tabla que no esta en el FROM y la query revienta.
 * ---------------------------------------------------------------------------
 */
class ClienteOrden extends Model
{
    /** Alias del fromSub. Tabla VIRTUAL: no existe en la BD. */
    protected $table = 'cliente_ordenes';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $casts = [
        'fecha' => 'datetime',
        'referencia_id' => 'integer',
        'unidades' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function () {
            throw new LogicException('ClienteOrden es de solo lectura: `cliente_ordenes` es una tabla virtual (UNION), no existe en la BD.');
        });

        static::deleting(function () {
            throw new LogicException('ClienteOrden es de solo lectura: `cliente_ordenes` es una tabla virtual (UNION), no existe en la BD.');
        });
    }

    /**
     * El UNION ALL de las dos ramas, envuelto en un builder Eloquent.
     *
     * ¡OJO! Las ramas de un UNION se emparejan por POSICION, no por nombre, y
     * los nombres de columna resultantes son los de la PRIMERA rama. Son DIEZ
     * columnas: si anades o quitas una, hazlo en las DOS y en el MISMO ORDEN.
     *
     * Sin orderBy aqui: applySorting() de rappasoft ACUMULA orderBy en vez de
     * reemplazarlos, asi que un orden fijo aqui dejaria el del usuario como
     * criterio secundario. El orden va en setDefaultSort().
     */
    public static function paraCliente(int $clienteId): Builder
    {
        // 1) VENTAS DE TELEFONOS
        //
        // `total_repuestos` es lo cobrado en piezas montadas en el equipo, que va
        // por encima del total del telefono. Subconsulta y no join: un join a
        // ventas_repuestos duplicaria la venta por cada cabecera enlazada y
        // multiplicaria todos los importes.
        $ventas = DB::table('ventas as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 'v.sucursal_id', '=', 's.id')
            ->where('v.cliente_id', $clienteId)
            ->selectRaw("
                CONCAT('VP-', v.id)                           as id,
                'Productos'                                   as tipo,
                v.id                                          as referencia_id,
                v.created_at                                  as fecha,
                (SELECT COUNT(*) FROM ventas_productos vp WHERE vp.venta_id = v.id)
                                                              as unidades,
                v.total                                       as total,
                v.total_bs                                    as total_bs,
                COALESCE((SELECT SUM(vr.total) FROM ventas_repuestos vr WHERE vr.venta_id = v.id), 0)
                                                              as total_repuestos,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 2) VENTAS DE REPUESTOS Y ACCESORIOS, SOLO LAS SUELTAS
        //
        // whereNull('venta_id'): ver el docblock de la clase. `total_repuestos` va
        // en cero porque el total de la fila YA es el de los repuestos.
        $repuestos = DB::table('ventas_repuestos as v')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 'v.sucursal_id', '=', 's.id')
            ->where('v.cliente_id', $clienteId)
            ->whereNull('v.venta_id')
            ->selectRaw("
                CONCAT('VR-', v.id)                           as id,
                'Repuestos'                                   as tipo,
                v.id                                          as referencia_id,
                v.created_at                                  as fecha,
                v.cantidad_repuestos                          as unidades,
                v.total                                       as total,
                v.total_bs                                    as total_bs,
                0                                             as total_repuestos,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        $union = $ventas->unionAll($repuestos);

        // El alias DEBE ser 'cliente_ordenes' (= $table). Ver docblock.
        return static::query()->fromSub($union, 'cliente_ordenes');
    }

    /** Lo que de verdad entro por esta orden: el documento mas sus repuestos. */
    public function totalCobrado(): float
    {
        return round((float) $this->total + (float) $this->total_repuestos, 2);
    }

    /** A donde lleva la fila para ver el detalle. */
    public function rutaDetalle(): string
    {
        return $this->tipo === 'Productos'
            ? route('ventas.detalles', $this->referencia_id)
            // No hay pantalla de detalle de venta de repuestos en el sistema: la
            // de editar es la unica que muestra sus lineas. Queda anotado.
            : route('ventas.repuestos.editar', $this->referencia_id);
    }
}
