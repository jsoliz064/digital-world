<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use App\Enums\LineaTipo;
use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de ventas_detalles (los cobros de taller los agrega
 * RepuestosDeReparacionService, que llama este servicio).
 *
 * Una venta lleva equipos (cantidad 1, con garantia), repuestos y accesorios
 * (con cantidad). Las reglas que no pueden perderse:
 *
 *  - El COSTO se relee de la base, nunca del formulario: del equipo bloqueado
 *    (costo_total, que ya incluye regalos y reparaciones) o del articulo. Antes
 *    VentaCreate tomaba el costo del payload de Livewire, que el cliente puede
 *    alterar.
 *  - Orden de bloqueo identico en todos los flujos: equipos por id, despues
 *    stock por (tipo, id). Dos ventas simultaneas con las mismas lineas en otro
 *    orden se bloquearian mutuamente en InnoDB.
 *  - El stock sale de la sucursal de la venta y se congela en la linea:
 *    anularla lo devuelve ahi.
 *  - Ningun metodo abre transaccion: la abre el componente, que tambien
 *    resuelve la idempotencia (GuardadoIdempotenteTrait).
 *
 * Formato de una linea que llega de la pantalla:
 *   ['tipo' => 'Producto'|'Repuesto'|'Accesorio', 'id' => int, 'cantidad' => int,
 *    'precio' => float, 'descuento' => float, 'garantia_meses' => ?int]
 */
class VentaService
{
    public function __construct(
        private EstadoProductoService $estados,
        private StockService $stock,
        private RepuestosDeReparacionService $cobros,
    ) {}

    /**
     * Registra una venta completa.
     *
     * @param  array  $cabecera  sucursal_id, cliente_id, cliente (texto), descuento, mano_obra
     * @param  array  $lineas    ver el docblock de la clase
     * @param  array  $cobros    cobros de taller (formato de RepuestosDeReparacionService)
     */
    public function registrar(array $cabecera, array $lineas, array $cobros, User $user, ?string $clave = null): Venta
    {
        $lineas = $this->normalizar($lineas);
        $sucursalId = (int) $cabecera['sucursal_id'];

        // Se calculan los totales ANTES de crear la cabecera: asi la bitacora de
        // la venta registra una fila con los totales reales, y no un 'creado'
        // en cero seguido de un 'editado'. recalcularTotales() al final lo
        // confirma desde la base (si coincide, el Observer no escribe nada).
        [$equipos, $articulos] = $this->leerCostos($lineas);

        $venta = Venta::create([
            'clave_idempotencia' => $clave,
            'sucursal_id' => $sucursalId,
            'cliente_id' => $cabecera['cliente_id'] ?? null,
            'cliente' => $cabecera['cliente'] ?? null,
            'user_id' => $user->id,
            'descuento' => round((float) ($cabecera['descuento'] ?? 0), 2),
            'mano_obra' => round((float) ($cabecera['mano_obra'] ?? 0), 2),
        ] + $this->totalesPrevios($lineas, $equipos, $articulos, $cabecera));

        foreach ($lineas->where('tipo', LineaTipo::Producto->value)->sortBy('id') as $linea) {
            $this->venderEquipo($venta, $linea);
        }

        foreach ($lineas->where('tipo', '!=', LineaTipo::Producto->value)->sortBy(fn($l) => $l['tipo'] . ':' . str_pad($l['id'], 12, '0', STR_PAD_LEFT)) as $linea) {
            $this->venderArticulo($venta, $linea, $articulos[$linea['tipo'] . ':' . $linea['id']]);
        }

        $this->cobros->registrar($venta, $cobros);

        $this->stock->recalcularTotales();
        $venta->recalcularTotales();

        return $venta;
    }

    /**
     * Edita una venta: cabecera (cliente, descuento, mano de obra) y lineas.
     * La sucursal NO cambia (el stock ya salio de ella) y `user_id` tampoco: el
     * vendedor es quien vendio, no quien corrige.
     *
     * Compara por articulo: lo que ya no esta se anula (devuelve stock o el
     * equipo a Inventario), lo que cambio de cantidad se ajusta con
     * ajustarSalida(), y lo nuevo se vende. Las cantidades "antes" se releen de
     * la base, no del formulario.
     */
    public function actualizar(Venta $venta, array $cabecera, array $lineas, array $cobros): Venta
    {
        $lineas = $this->normalizar($lineas)->keyBy(fn($l) => $l['tipo'] . ':' . $l['id']);
        $actuales = $venta->detalles()->whereNull('producto_reparacion_repuesto_id')->get()
            ->keyBy(fn($d) => $d->tipo . ':' . $d->{LineaTipo::from($d->tipo)->columna()});

        // 1. Lo que ya no esta: anular, equipos primero (descobrando antes).
        foreach ($actuales->diffKeys($lineas)->sortBy(fn($d) => $d->producto_id ? 0 : 1) as $detalle) {
            if ($detalle->producto_id) {
                $this->cobros->cancelarCobros($venta, $detalle->producto_id);
                $detalle->delete();
                $this->estados->cambiar(
                    $detalle->producto_id,
                    array_map(fn($v) => ProductoEstado::from($v), ProductoEstado::vendidos()),
                    ProductoEstado::Inventario,
                    "Producto devuelto al inventario: se quito de la venta #{$venta->id}.",
                    ['venta_id' => $venta->id],
                );
            } else {
                $tipo = $detalle->tipoLinea()->articulo();
                $this->stock->ingresar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
                $detalle->delete();
            }
        }

        // 2. Lo que sigue: precio, descuento, garantia y (articulos) cantidad.
        foreach ($actuales->intersectByKeys($lineas) as $clave => $detalle) {
            $linea = $lineas[$clave];

            if ($detalle->producto_id) {
                $detalle->update($this->importesEquipo($linea, (float) $detalle->costo) + $this->garantia($linea));
                continue;
            }

            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->ajustarSalida($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad, $linea['cantidad']);
            $detalle->update($this->importesArticulo($linea, (float) $detalle->costo));
        }

        // 3. Lo nuevo.
        $nuevas = $lineas->diffKeys($actuales);
        [, $articulos] = $this->leerCostos($nuevas);

        foreach ($nuevas->where('tipo', LineaTipo::Producto->value)->sortBy('id') as $linea) {
            $this->venderEquipo($venta, $linea);
        }

        foreach ($nuevas->where('tipo', '!=', LineaTipo::Producto->value) as $clave => $linea) {
            $this->venderArticulo($venta, $linea, $articulos[$clave]);
        }

        $this->cobros->registrar($venta, $cobros);

        $venta->fill([
            'cliente_id' => $cabecera['cliente_id'] ?? null,
            'cliente' => $cabecera['cliente'] ?? $venta->cliente,
            'descuento' => round((float) ($cabecera['descuento'] ?? 0), 2),
            'mano_obra' => round((float) ($cabecera['mano_obra'] ?? 0), 2),
        ]);

        if ($venta->detalles()->doesntExist()) {
            throw ValidationException::withMessages([
                'detalles' => 'La venta tiene que tener al menos una línea. Para quitarlo todo, anula la venta.',
            ]);
        }

        $this->stock->recalcularTotales();
        $venta->recalcularTotales();

        return $venta;
    }

    // ------------------------------------------------------------------ lineas

    private function venderEquipo(Venta $venta, array $linea): void
    {
        // vender() bloquea el producto, exige que este disponible (y no dado de
        // baja) y anota en su bitacora desde el mismo $destino que el update.
        $producto = $this->estados->vender(
            $linea['id'],
            "Vendido. Venta #{$venta->id}" . ($venta->nombreCliente() ? ', ' . $venta->nombreCliente() : '')
                . ', Bs ' . number_format(max(0, $linea['precio'] - $linea['descuento']), 2),
            ['venta_id' => $venta->id, 'sucursal_id' => $venta->sucursal_id],
        );

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'sucursal_id' => $venta->sucursal_id,
            'cantidad' => 1,
            'tipo_venta' => $producto->tipo_venta,
        ] + $this->importesEquipo($linea, (float) $producto->costo_total) + $this->garantia($linea));
    }

    private function venderArticulo(Venta $venta, array $linea, float $costo): void
    {
        $tipo = LineaTipo::from($linea['tipo'])->articulo();

        // El WHERE cantidad >= ? de retirar() es la validacion de stock.
        $this->stock->retirar($tipo, $linea['id'], $venta->sucursal_id, $linea['cantidad']);

        VentaDetalle::create([
            'venta_id' => $venta->id,
            $tipo->columna() => $linea['id'],
            'sucursal_id' => $venta->sucursal_id,
        ] + $this->importesArticulo($linea, $costo));
    }

    private function importesEquipo(array $linea, float $costo): array
    {
        $precio = round($linea['precio'], 2);
        $descuento = round(min($linea['descuento'], $precio), 2);

        return [
            'cantidad' => 1,
            'costo' => $costo,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => round($precio - $descuento, 2),
            'subtotal_costo' => $costo,
        ];
    }

    private function importesArticulo(array $linea, float $costo): array
    {
        $cantidad = $linea['cantidad'];
        $precio = round($linea['precio'], 2);
        $bruto = round($precio * $cantidad, 2);
        $descuento = round(min($linea['descuento'], $bruto), 2);

        return [
            'cantidad' => $cantidad,
            'costo' => $costo,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => round($bruto - $descuento, 2),
            'subtotal_costo' => round($costo * $cantidad, 2),
        ];
    }

    private function garantia(array $linea): array
    {
        $meses = $linea['garantia_meses'] ?? null;

        return [
            'garantia_meses' => $meses ?: null,
            'garantia_fecha_exp' => $meses ? now()->addMonths((int) $meses)->toDateString() : null,
        ];
    }

    // ------------------------------------------------------------------ apoyo

    /**
     * Valida y normaliza las lineas que llegan de la pantalla. Un equipo
     * siempre tiene cantidad 1 (tambien lo exige un CHECK en la base).
     */
    private function normalizar(array $lineas): Collection
    {
        $lineas = collect($lineas)->map(function ($l) {
            $tipo = LineaTipo::tryFrom((string) ($l['tipo'] ?? ''));
            $id = (int) ($l['id'] ?? 0);

            if (!$tipo || $id <= 0) {
                throw ValidationException::withMessages(['detalles' => 'Hay una línea de venta inválida. Recarga la pantalla.']);
            }

            $cantidad = $tipo === LineaTipo::Producto ? 1 : (int) ($l['cantidad'] ?? 0);

            if ($cantidad < 1) {
                throw ValidationException::withMessages(['detalles' => 'Cada línea tiene que tener al menos una unidad.']);
            }

            $precio = (float) ($l['precio'] ?? 0);
            $descuento = (float) ($l['descuento'] ?? 0);

            if ($precio < 0 || $descuento < 0) {
                throw ValidationException::withMessages(['detalles' => 'Los precios y descuentos no pueden ser negativos.']);
            }

            return [
                'tipo' => $tipo->value,
                'id' => $id,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descuento' => $descuento,
                'garantia_meses' => $tipo === LineaTipo::Producto ? ($l['garantia_meses'] ?? null) : null,
            ];
        });

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => 'Agrega al menos un producto, repuesto o accesorio a la venta.']);
        }

        // Un mismo articulo dos veces en la venta: se rechaza aqui con mensaje,
        // aunque el UNIQUE vd_venta_articulo_unico tambien lo impediria.
        if ($lineas->unique(fn($l) => $l['tipo'] . ':' . $l['id'])->count() !== $lineas->count()) {
            throw ValidationException::withMessages(['detalles' => 'Hay un producto repetido en la venta: suma la cantidad en una sola línea.']);
        }

        return $lineas->values();
    }

    /**
     * Lee de la base el costo de lo que se va a vender, para los totales previos
     * y para las lineas de articulo. El de los equipos se vuelve a leer, ya
     * bloqueado, en venderEquipo().
     *
     * @return array{0: array<int,float>, 1: array<string,float>}
     */
    private function leerCostos(Collection $lineas): array
    {
        $equipos = Producto::whereKey($lineas->where('tipo', LineaTipo::Producto->value)->pluck('id'))
            ->pluck('costo_total', 'id')->map(fn($c) => (float) $c)->all();

        $articulos = [];
        foreach ([ArticuloTipo::Repuesto, ArticuloTipo::Accesorio] as $tipo) {
            $ids = $lineas->where('tipo', $tipo->value)->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $costos = ($tipo->modelo())::whereKey($ids)->pluck('costo', 'id');

            foreach ($ids as $id) {
                if (!isset($costos[$id])) {
                    throw ValidationException::withMessages(['detalles' => "Un {$tipo->label()} de la venta ya no existe. Recarga la pantalla."]);
                }
                $articulos[$tipo->value . ':' . $id] = (float) $costos[$id];
            }
        }

        return [$equipos, $articulos];
    }

    private function totalesPrevios(Collection $lineas, array $equipos, array $articulos, array $cabecera): array
    {
        $subtotal = 0.0;
        $costo = 0.0;

        foreach ($lineas as $linea) {
            if ($linea['tipo'] === LineaTipo::Producto->value) {
                $i = $this->importesEquipo($linea, $equipos[$linea['id']] ?? 0);
            } else {
                $i = $this->importesArticulo($linea, $articulos[$linea['tipo'] . ':' . $linea['id']] ?? 0);
            }
            $subtotal += $i['subtotal'];
            $costo += $i['subtotal_costo'];
        }

        $manoObra = round((float) ($cabecera['mano_obra'] ?? 0), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal - (float) ($cabecera['descuento'] ?? 0) + $manoObra, 2),
            'costo_total' => round($costo + $manoObra, 2),
        ];
    }
}
