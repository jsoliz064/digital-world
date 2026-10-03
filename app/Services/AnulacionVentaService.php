<?php

namespace App\Services;

use App\Enums\ProductoEstado;
use App\Models\VentaProducto;

/**
 * Anular la linea de venta de un telefono: el unico camino.
 *
 * POR QUE EXISTE
 * Habia dos pantallas que hacen esto -- VentaDetalleDestroyModal, desde el
 * detalle de la venta, y ProductoEstadoModal::cancelarVenta(), desde la ficha
 * del producto-- y eran gemelas divergidas. La del producto hacia las tres
 * cosas bien; la de la venta se dejaba las tres:
 *
 *   1. No llamaba a cancelarCobros(), asi que los repuestos cobrados con el
 *      equipo seguian cobrados y sus reparaciones marcadas como no elegibles.
 *      La FK nullOnDelete dejaba el cobro vivo con venta_id nulo.
 *   2. No borraba la cabecera cuando el detalle era el ultimo, y dejaba una
 *      venta de total 0 y cero productos -- que es EXACTAMENTE lo que un
 *      usuario describe como "la venta no se guardo".
 *   3. Devolvia el producto a Inventario con un update directo, sin precondicion
 *      ni bloqueo.
 *
 * Un gemelo que ya divergio una vez vuelve a divergir, asi que aqui hay uno.
 *
 * TRANSACCIONES
 * No abre la suya: asume la del llamador. Anular toca tres tablas y media
 * anulacion es peor que ninguna.
 */
class AnulacionVentaService
{
    public function __construct(
        private EstadoProductoService $estados,
        private RepuestosDeReparacionService $repuestos,
    ) {}

    /**
     * Quita el telefono de su venta y lo devuelve al inventario.
     *
     * Si era el unico producto de la venta, la venta entera desaparece: una
     * cabecera sin lineas no es un documento, es basura que ensucia los
     * listados y los reportes.
     */
    public function anularDetalle(VentaProducto $detalle): void
    {
        $venta = $detalle->venta;
        $producto = $detalle->producto;

        // El id se guarda ANTES: si la venta se borra, $venta->id deja de
        // servir para el mensaje del historial.
        $ventaId = $venta->id;

        // Se descobra ANTES de tocar la venta: si la venta desaparece primero,
        // la FK nullOnDelete deja el cobro de repuestos huerfano, vivo y cobrado
        // a un cliente cuya venta se acaba de anular.
        if ($venta->detalles()->count() < 2) {
            $this->repuestos->cancelarCobros($venta);

            // La FK de ventas_productos.venta_id es cascade: borrar la cabecera
            // se lleva este detalle. No hace falta borrarlo a mano.
            $venta->delete();
        } else {
            // Con varios productos solo se anula este: sus repuestos se
            // descobran, los de los demas siguen cobrados.
            $this->repuestos->cancelarCobros($venta, $producto->id);

            $detalle->delete();
            $venta->refresh();
            $venta->recalcularTotal();
        }

        // La precondicion importa aqui tambien: si el producto ya no esta
        // Vendido, alguien lo movio y volver a bajarlo a Inventario pisaria ese
        // cambio.
        $this->estados->cambiar(
            $producto->id,
            ProductoEstado::Vendido,
            ProductoEstado::Inventario,
            "Producto devuelto al inventario desde la venta #{$ventaId} (cancelacion de detalle).",
        );

        // No se restaura disponible_catalogo, y es a proposito: nadie guardo su
        // valor anterior. Las tres puertas de venta lo ponian en false al
        // vender, lo cual era redundante -- el catalogo excluye Vendido con un
        // whereNotIn y su condicion es `disponible_catalogo = 1 OR estado IN
        // (Inventario, Oferta)` -- y destructivo, porque borraba una casilla que
        // el operador marca a mano. Esos writes se quitaron en lugar de
        // inventar aqui un valor que no se sabe.
    }
}
