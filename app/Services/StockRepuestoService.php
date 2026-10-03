<?php

namespace App\Services;

use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * El UNICO camino de escritura del stock de repuestos y accesorios.
 *
 * Antes de existir, el stock se movia con increment()/decrement() directos desde
 * DIECISEIS sitios en ocho componentes, y el docblock de RepuestoMovimiento ya
 * lo señalaba: "en este sistema NO hay kardex de repuestos". Unificar la
 * escritura es lo que permite agregarle la sucursal una sola vez en lugar de
 * dieciseis, y lo que vuelve el refactor verificable: una funcion que probar.
 *
 * POR QUE NO HAY UN mover($delta) CON SIGNO
 * El fallo que mas veces aparecio en este modulo es un signo copiado del modulo
 * de al lado. Hoy CompraRepuestoEdit calcula (nuevo - original) y
 * VentaRepuestoEdit calcula (original - nuevo): LAS DOS SON CORRECTAS para su
 * documento, y por eso nadie las corrige y por eso se copian mal -- es
 * literalmente el bug que documenta el comentario de CompraRepuestoEdit, donde
 * un decrement copiado de ventas restaba inventario al añadir una linea a una
 * compra. Aqui las cantidades son SIEMPRE positivas y la direccion la pone el
 * NOMBRE del metodo, que coincide con el tipo de documento del llamador y por
 * tanto nunca es ambiguo leyendo una sola linea.
 *
 * TRANSACCIONES
 * Ningun metodo abre la suya: asumen la del llamador. Una venta que falla a
 * mitad tiene que revertir el stock Y el documento, y eso solo pasa si comparten
 * transaccion.
 *
 * USO
 *   $stock->retirar($repuestoId, $sucursalId, 3);
 *   ... mas movimientos ...
 *   $stock->recalcularTotales();   // UNA vez, al final, antes de cerrar
 */
class StockRepuestoService
{
    /** Ids de repuesto tocados, para recalcular el total una sola vez cada uno. */
    private array $tocados = [];

    // ------------------------------------------------------------------ entradas

    /**
     * Suma unidades al stock de una sucursal. Compras, devoluciones y el destino
     * de una transferencia.
     */
    public function ingresar(int $repuestoId, ?int $sucursalId, int $cantidad): void
    {
        // Corte obligatorio, y es la linea mas importante de la clase: al editar
        // una venta vieja solo para cambiar el precio, su linea pasa por
        // ajustarSalida(antes: 5, ahora: 5) -> delta 0. Si eso llegara al UPDATE
        // de retirar(), con la fila de esa sucursal inexistente tras el backfill
        // daria un falso "sin stock" y NINGUNA venta vieja se podria volver a
        // guardar.
        if ($cantidad === 0) {
            return;
        }

        $this->exigirPositiva($cantidad);
        $sucursalId = $this->exigirSucursal($sucursalId);

        // Una sola sentencia: crea la fila si no existe y suma si existe. Sin
        // SELECT previo no hay carrera que resolver ni savepoint que gastar.
        // No se usa VALUES(): esta deprecado desde MySQL 8.0.20, y el parametro
        // repetido funciona en cualquier version.
        DB::statement(
            'INSERT INTO repuestos_sucursales
                 (repuesto_id, sucursal_id, cantidad, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cantidad = cantidad + ?, updated_at = ?',
            [$repuestoId, $sucursalId, $cantidad, now(), now(), $cantidad, now()]
        );

        $this->tocados[$repuestoId] = true;
    }

    // ------------------------------------------------------------------- salidas

    /**
     * Resta unidades del stock de una sucursal. Ventas, reparaciones, el origen
     * de una transferencia, y revertir una linea de compra.
     *
     * @throws ValidationException si esa sucursal no tiene las unidades.
     */
    public function retirar(int $repuestoId, ?int $sucursalId, int $cantidad): void
    {
        if ($cantidad === 0) {
            return;                       // ver el comentario de ingresar()
        }

        $this->exigirPositiva($cantidad);
        $sucursalId = $this->exigirSucursal($sucursalId);

        // El `WHERE cantidad >= ?` ES la validacion de stock, y es atomica:
        // InnoDB toma el lock de la fila dentro de la propia sentencia. Un
        // disponible() leido antes y un UPDATE despues dejan hueco para que otro
        // vendedor se lleve las mismas unidades en medio.
        //
        // 0 filas afectadas significa "no hay fila" O "no alcanza". Las dos se
        // cuentan igual y el mensaje las cubre.
        $afectadas = DB::update(
            'UPDATE repuestos_sucursales
                SET cantidad = cantidad - ?, updated_at = ?
              WHERE repuesto_id = ? AND sucursal_id = ? AND cantidad >= ?',
            [$cantidad, now(), $repuestoId, $sucursalId, $cantidad]
        );

        if ($afectadas === 0) {
            throw ValidationException::withMessages([
                'detalles' => $this->mensajeSinStock($repuestoId, $sucursalId, $cantidad),
            ]);
        }

        $this->tocados[$repuestoId] = true;
    }

    // -------------------------------------------------------------- ajustes 1:1
    //
    // Estos dos existen para que NINGUN llamador escriba una resta. La asimetria
    // entre compras y ventas vive aqui, en el nombre del metodo, y no repartida
    // en cuatro sitios con dos ordenes distintos.

    /** Compras: subir la cantidad de una linea INGRESA mas stock. */
    public function ajustarEntrada(int $repuestoId, ?int $sucursalId, int $antes, int $ahora): void
    {
        $delta = $ahora - $antes;

        $delta >= 0
            ? $this->ingresar($repuestoId, $sucursalId, $delta)
            : $this->retirar($repuestoId, $sucursalId, -$delta);
    }

    /** Ventas y reparaciones: subir la cantidad de una linea RETIRA mas stock. */
    public function ajustarSalida(int $repuestoId, ?int $sucursalId, int $antes, int $ahora): void
    {
        $delta = $ahora - $antes;

        $delta >= 0
            ? $this->retirar($repuestoId, $sucursalId, $delta)
            : $this->ingresar($repuestoId, $sucursalId, -$delta);
    }

    // -------------------------------------------------------------- transferencia

    /**
     * Mueve unidades de una sucursal a otra y deja el documento.
     *
     * @throws ValidationException si el origen no tiene stock o coincide con el destino.
     */
    public function transferir(int $repuestoId, int $origenId, int $destinoId, int $cantidad, ?int $userId): void
    {
        if ($origenId === $destinoId) {
            throw ValidationException::withMessages([
                'sucursal2_id' => 'El origen y el destino tienen que ser sucursales distintas.',
            ]);
        }

        $this->exigirPositiva($cantidad);

        if ($cantidad === 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'Indica cuantas unidades transferir.',
            ]);
        }

        // Retirar PRIMERO: si no hay stock, la excepcion revierte antes de haber
        // inventado nada en el destino.
        $this->retirar($repuestoId, $origenId, $cantidad);
        $this->ingresar($repuestoId, $destinoId, $cantidad);

        DB::table('repuestos_transferencias')->insert([
            'repuesto_id' => $repuestoId,
            'sucursal_origen_id' => $origenId,
            'sucursal_destino_id' => $destinoId,
            'cantidad' => $cantidad,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ lectura

    /**
     * Stock de una sucursal. Sirve para pintar pantalla y para avisar antes de
     * confirmar; NO es la puerta. La validacion de verdad vive dentro del UPDATE
     * de retirar(), porque entre un SELECT y un UPDATE cabe otra venta.
     */
    public function disponible(int $repuestoId, ?int $sucursalId): int
    {
        if ($sucursalId === null) {
            return 0;
        }

        return (int) DB::table('repuestos_sucursales')
            ->where('repuesto_id', $repuestoId)
            ->where('sucursal_id', $sucursalId)
            ->value('cantidad');
    }

    /** El stock de un repuesto en todas sus sucursales: [sucursal_id => cantidad]. */
    public function porSucursal(int $repuestoId): array
    {
        return DB::table('repuestos_sucursales')
            ->where('repuesto_id', $repuestoId)
            ->pluck('cantidad', 'sucursal_id')
            ->map(fn($c) => (int) $c)
            ->all();
    }

    // ----------------------------------------------------------- total cacheado

    /**
     * Reescribe repuestos.cantidad desde la subtabla, para los repuestos tocados
     * en esta request.
     *
     * Es una sentencia por repuesto, idempotente y AUTO-SANANTE: si el total
     * derivo por cualquier via -- el update a mano que hacia el modal de
     * edicion, un movimiento viejo -- esto lo cuadra.
     *
     * NO se usa un increment paralelo sobre repuestos.cantidad: seria un segundo
     * contador capaz de divergir del primero sin forma de saber cual miente, o
     * sea exactamente el bug que la subtabla viene a matar.
     *
     * Se llama UNA vez al final y no una por movimiento: CompraRepuestoEdit
     * puede mover el mismo repuesto dos veces en un mismo guardado.
     */
    public function recalcularTotales(): void
    {
        foreach (array_keys($this->tocados) as $repuestoId) {
            DB::update(
                'UPDATE repuestos
                    SET cantidad = COALESCE(
                            (SELECT SUM(cantidad) FROM repuestos_sucursales WHERE repuesto_id = ?), 0
                        ),
                        updated_at = ?
                  WHERE id = ?',
                [$repuestoId, now(), $repuestoId]
            );
        }

        $this->tocados = [];
    }

    // ------------------------------------------------------------------- guardas

    private function exigirPositiva(int $cantidad): void
    {
        // InvalidArgumentException y no ValidationException: una cantidad
        // negativa aqui no es culpa del usuario, es un llamador que calculo un
        // signo, que es justo lo que estos metodos prohiben.
        if ($cantidad < 0) {
            throw new InvalidArgumentException(
                "StockRepuestoService: las cantidades son siempre positivas, llego {$cantidad}. "
                . 'Para una diferencia usa ajustarEntrada() o ajustarSalida().'
            );
        }
    }

    /**
     * productos.sucursal_id y ventas_repuestos_detalles.sucursal_id son nullable
     * con onDelete('set null'), asi que un null llega de verdad. Se corta con
     * mensaje en vez de crear una fila huerfana: la unique de
     * (repuesto_id, sucursal_id) NO colisiona entre NULLs en MySQL, asi que
     * tendriamos una fila de stock sin sucursal por cada movimiento.
     */
    private function exigirSucursal(?int $sucursalId): int
    {
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'detalles' => 'Ese movimiento no tiene sucursal asignada, y el stock se lleva por '
                    . 'sucursal. Asigna la sucursal del documento antes de guardar.',
            ]);
        }

        return $sucursalId;
    }

    private function mensajeSinStock(int $repuestoId, int $sucursalId, int $pedido): string
    {
        $nombre = Repuesto::whereKey($repuestoId)->value('nombre') ?? "#{$repuestoId}";
        $sucursal = DB::table('sucursales')->where('id', $sucursalId)->value('nombre') ?? "#{$sucursalId}";
        $hay = $this->disponible($repuestoId, $sucursalId);

        return "No hay stock de «{$nombre}» en {$sucursal}: se piden {$pedido} y hay {$hay}.";
    }
}
