<?php

namespace App\Services;

use App\Enums\ReparacionTipo;
use App\Models\Bitacora;
use App\Models\ProductoReparacionRepuesto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaRepuesto;
use App\Models\VentaRepuestoDetalle;
use Illuminate\Support\Collection;

/**
 * Cobrar como venta los repuestos que se montaron en las reparaciones de un
 * telefono antes de venderlo.
 *
 * El caso: el cliente quiere el equipo con la bateria al 100%. El telefono pasa
 * a reparacion, se le monta una bateria, y hoy esa pieza solo infla el costo
 * del telefono. Nunca existe como venta, asi que no aparece en el historial del
 * repuesto ni suma al ingreso, y el margen del equipo sale castigado por un
 * costo que en realidad el cliente pago.
 *
 * Dos reglas gobiernan todo lo que hay aqui:
 *
 *  1. NO se toca el stock. La pieza salio del almacen cuando el tecnico la
 *     monto (ProductoEstadoModal, ProductoReparacionClienteModal y
 *     ReparacionEditModal hacen decrement() al escribir la linea de
 *     reparacion). Volver a descontar la restaria dos veces.
 *
 *  2. La linea se escribe con COSTO CERO. Ese costo ya viaja dentro de
 *     `productos.costo_total`, congelado en `ventas_productos.costo` al vender.
 *     La ganancia de repuestos se calcula como SUM(d.subtotal_costo) en tres
 *     formulas de ReporteIndex y como SUM(costo_total) en VentaRepuestoIndex;
 *     con el costo en cero las cuatro quedan correctas sin tocar ni una
 *     consulta. El repuesto aporta ingreso puro, que es justo lo que es: el
 *     cliente lo paga encima del precio del telefono.
 *
 * Lo usan las tres puertas de venta (VentaCreate, VentaEdit y
 * ProductoEstadoModal), por eso es un servicio y no un trait del carrito:
 * ProductoEstadoModal no tiene carrito.
 */
class RepuestosDeReparacionService
{
    /**
     * Lineas de reparacion de un producto que todavia se pueden cobrar.
     *
     * Solo reparaciones Normal: en una Garantia la pieza se le puso gratis al
     * cliente, y un Trabajo Externo ya tiene su propio `cobro_cliente`. Cobrar
     * cualquiera de las dos seria cobrar dos veces.
     */
    public function elegibles(int $productoId): Collection
    {
        return ProductoReparacionRepuesto::query()
            ->with(['repuesto:id,nombre,fabricante,precio,tipo', 'reparacion:id,producto_id,tipo,created_at'])
            ->whereHas('reparacion', fn($query) => $query
                ->where('producto_id', $productoId)
                ->where('tipo', ReparacionTipo::Normal->value))
            ->whereNotIn('id', $this->yaCobrados())
            ->orderBy('id')
            ->get();
    }

    /** Cuantas lineas cobrables tiene el producto. Para pintar el boton. */
    public function contarElegibles(int $productoId): int
    {
        return ProductoReparacionRepuesto::query()
            ->whereHas('reparacion', fn($query) => $query
                ->where('producto_id', $productoId)
                ->where('tipo', ReparacionTipo::Normal->value))
            ->whereNotIn('id', $this->yaCobrados())
            ->count();
    }

    /**
     * Ids de linea de reparacion que ya se cobraron alguna vez.
     *
     * La columna es UNICA en la base, asi que esto es comodidad para la
     * pantalla: quien impide de verdad el doble cobro es el indice.
     */
    private function yaCobrados()
    {
        return VentaRepuestoDetalle::query()
            ->whereNotNull('producto_reparacion_repuesto_id')
            ->select('producto_reparacion_repuesto_id');
    }

    /**
     * Registra la venta de repuestos enlazada a la venta de un telefono.
     *
     * Se llama DENTRO de la transaccion de la venta: si algo falla aqui, la
     * venta entera revierte y no queda media operacion.
     *
     * @param  array  $lineas  cada una con producto_reparacion_repuesto_id y precio
     */
    public function registrar(Venta $venta, array $lineas, User $user): ?VentaRepuesto
    {
        $lineas = $this->normalizar($lineas);

        if (empty($lineas)) {
            return null;
        }

        // Se releen las lineas de reparacion de la base en vez de fiarse de lo
        // que llego de la pantalla: la cantidad y el repuesto salen de aqui, y
        // solo el precio viene del vendedor.
        $origenes = ProductoReparacionRepuesto::with('repuesto', 'reparacion.producto')
            ->whereIn('id', array_keys($lineas))
            ->get()
            ->keyBy('id');

        if ($origenes->isEmpty()) {
            return null;
        }

        $subtotal = 0;
        foreach ($origenes as $origen) {
            $subtotal += round($lineas[$origen->id] * $origen->cantidad, 2);
        }
        $subtotal = round($subtotal, 2);

        $ventaRepuesto = VentaRepuesto::create([
            'cantidad_repuestos' => $origenes->count(),
            'subtotal' => $subtotal,
            'descuento' => 0,
            'mano_obra' => 0,
            // Costo cero a proposito: lo carga el telefono. Ver la cabecera.
            'costo_total' => 0,
            'costo_total_bs' => 0,
            'total' => $subtotal,
            'tipo_cambio' => $venta->tipo_cambio,
            'total_bs' => round($subtotal * $venta->tipo_cambio, 2),
            'cliente' => $venta->cliente,
            // El enlace, no solo el nombre: sin esta linea los repuestos cobrados
            // con un telefono quedarian sin dueno y no saldrian en el historial del
            // cliente, que va estrictamente por cliente_id.
            'cliente_id' => $venta->cliente_id,
            'user_id' => $user->id,
            'sucursal_id' => $venta->sucursal_id,
            'venta_id' => $venta->id,
        ]);

        foreach ($origenes as $origen) {
            $precio = $lineas[$origen->id];
            $subtotalLinea = round($precio * $origen->cantidad, 2);

            VentaRepuestoDetalle::create([
                'costo' => 0,
                'subtotal_costo' => 0,
                'subtotal_costo_bs' => 0,
                'precio' => $precio,
                'cantidad' => $origen->cantidad,
                'descuento' => 0,
                'subtotal' => $subtotalLinea,
                'tipo_cambio' => $venta->tipo_cambio,
                'subtotal_bs' => round($subtotalLinea * $venta->tipo_cambio, 2),
                'repuesto_id' => $origen->repuesto_id,
                // Tipo congelado, igual que en la venta de repuestos normal:
                // reclasificar el articulo manana no reescribe un periodo ya
                // cerrado.
                'tipo' => $origen->repuesto?->tipo,
                'venta_repuesto_id' => $ventaRepuesto->id,
                'sucursal_id' => $venta->sucursal_id,
                'producto_reparacion_repuesto_id' => $origen->id,
            ]);

            $nombre = $origen->repuesto?->nombre ?? 'Repuesto';

            // La nota va sobre el TELEFONO: es su historial el que tiene que
            // contar que esa pieza se cobro con su venta. Evento 'cobro' y no
            // 'Vendido': antes se escribia el estado a mano, y un evento que no
            // es un estado no puede contradecir al estado real.
            $producto = $origen->reparacion?->producto;

            if ($producto) {
                Bitacora::registrar(
                    $producto,
                    'cobro',
                    "Repuesto {$nombre} cobrado con la venta #{$venta->id}. "
                        . "Se monto en la reparacion #{$origen->producto_reparacion_id} y su stock ya se descontó entonces.",
                    [
                        'producto_reparacion_id' => $origen->producto_reparacion_id,
                        'venta_id' => $venta->id,
                        'repuesto_id' => $origen->repuesto_id,
                        'venta_repuesto_id' => $ventaRepuesto->id,
                    ],
                );
            }
        }

        return $ventaRepuesto;
    }

    /**
     * Deshace el cobro de repuestos de una venta, o solo el de UN producto.
     *
     * NO devuelve stock: las piezas siguen montadas en el telefono, se anule la
     * venta o no. Al borrar las lineas, sus reparaciones vuelven a quedar
     * cobrables, que es lo que debe pasar si esa venta ya no existe.
     *
     * Sin esto, la FK nullOnDelete dejaba la venta de repuestos viva con
     * `venta_id` nulo: un cobro huerfano a un cliente cuya venta se cancelo, y
     * las piezas marcadas como cobradas para siempre.
     */
    public function cancelarCobros(Venta $venta, ?int $productoId = null): void
    {
        $cabeceras = VentaRepuesto::with('detalles.repuesto', 'detalles.reparacionRepuesto.reparacion.producto')
            ->where('venta_id', $venta->id)
            ->get();

        foreach ($cabeceras as $cabecera) {
            $aBorrar = $cabecera->detalles->filter(function ($detalle) use ($productoId) {
                if ($detalle->producto_reparacion_repuesto_id === null) {
                    return false;
                }

                // Cancelar UN producto de una venta con varios solo descobra lo
                // suyo; lo del resto sigue cobrado.
                return $productoId === null
                    || $detalle->reparacionRepuesto?->reparacion?->producto_id == $productoId;
            });

            if ($aBorrar->isEmpty()) {
                continue;
            }

            // Antes aqui se BORRABAN las filas de historial del cobro, como si
            // nunca hubiera ocurrido. La bitacora no borra: el cobro existio y
            // se deshizo, y las dos cosas quedan contadas.
            foreach ($aBorrar as $detalle) {
                $producto = $detalle->reparacionRepuesto?->reparacion?->producto;

                if ($producto) {
                    Bitacora::registrar(
                        $producto,
                        'cobro-anulado',
                        "Cobro del repuesto {$detalle->repuesto?->nombre} anulado: la venta #{$venta->id} ya no lo incluye. "
                            . 'La pieza sigue montada en el equipo.',
                        [
                            'venta_id' => $venta->id,
                            'repuesto_id' => $detalle->repuesto_id,
                            'venta_repuesto_id' => $cabecera->id,
                            'producto_reparacion_id' => $detalle->reparacionRepuesto?->producto_reparacion_id,
                        ],
                    );
                }
            }

            VentaRepuestoDetalle::destroy($aBorrar->pluck('id')->all());

            $cabecera->refresh();

            // Si no le queda nada que cobrar, la cabecera sobra.
            if ($cabecera->detalles->isEmpty()) {
                $cabecera->delete();
                continue;
            }

            $subtotal = round((float) $cabecera->detalles->sum('subtotal'), 2);
            $cabecera->update([
                'cantidad_repuestos' => $cabecera->detalles->count(),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'total_bs' => round($subtotal * $cabecera->tipo_cambio, 2),
            ]);
        }
    }

    /**
     * Aplana lo que llega de las pantallas a [linea_reparacion_id => precio].
     *
     * En la pantalla de venta las selecciones vienen agrupadas por producto,
     * y en el modal de estado llegan sueltas. Se descartan las lineas sin
     * precio positivo: una venta de cero no es una venta.
     */
    private function normalizar(array $lineas): array
    {
        $plano = [];

        foreach ($lineas as $entrada) {
            // Una lista de lineas, o un grupo de listas por producto.
            $grupo = isset($entrada['producto_reparacion_repuesto_id']) ? [$entrada] : $entrada;

            foreach ($grupo as $linea) {
                $id = (int) ($linea['producto_reparacion_repuesto_id'] ?? 0);
                $precio = round((float) ($linea['precio'] ?? 0), 2);

                if ($id > 0 && $precio > 0) {
                    $plano[$id] = $precio;
                }
            }
        }

        return $plano;
    }
}
