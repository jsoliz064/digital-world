<?php

namespace App\Traits;

use App\Models\Producto;
use App\Services\RepuestosDeReparacionService;
use Livewire\Attributes\On;

/**
 * El carrito de una venta de productos (celulares), compartido por la pantalla
 * de crear y la de editar.
 *
 * Las dos buscan por IMEI, agregan lineas, recalculan y bloquean el producto al
 * guardar de forma identica. Vivia duplicado en los dos modales y las copias ya
 * habian divergido -- el filtro por sucursal estaba solo en una-- asi que un
 * arreglo en una no llegaba a la otra.
 *
 * El componente que lo use debe declarar estas propiedades publicas:
 *   $searchImei, $filteredProductos, $detalles, $venta, $tipo_precio
 * donde $venta es el array de la cabecera con las claves tipo_cambio,
 * descuento, subtotal, total y total_bs.
 */
trait VentaCarritoTrait
{
    /**
     * Repuestos de reparacion que se cobran aparte, agrupados por producto.
     *
     *   producto_id => [ ['producto_reparacion_repuesto_id'=>…, 'nombre'=>…,
     *                     'cantidad'=>…, 'precio'=>…, 'subtotal'=>…], … ]
     *
     * Se declara aqui y no en cada componente porque nace con el trait; las
     * demas propiedades son anteriores y viven en los componentes.
     */
    public $repuestosVenta = [];

    /**
     * Sucursal a la que se limita la busqueda, o null para no limitarla.
     *
     * Crear no filtra (se puede vender un equipo de otra sucursal) y editar si,
     * porque la venta ya tiene su sucursal fijada. Se mantiene la asimetria que
     * habia antes del refactor.
     */
    protected function sucursalIdParaBusqueda(): ?int
    {
        return null;
    }

    /** Si una linea admite cambios de precio/descuento y se puede quitar. */
    protected function lineaEditable(array $detalle): bool
    {
        return true;
    }

    /** Si agregar un producto puede fijar el tipo de cambio de la venta. */
    protected function debeSembrarTipoCambio(): bool
    {
        return empty($this->detalles);
    }

    public function updatedSearchImei($value)
    {
        if (trim((string) $value) === '') {
            $this->filteredProductos = [];
            return;
        }

        $idsExistentes = collect($this->detalles)->pluck('producto_id')->toArray();

        $this->filteredProductos = Producto::buscarPorImei($value)
            ->disponibles()
            ->whereNotIn('id', $idsExistentes)
            ->when($this->sucursalIdParaBusqueda(), fn($q, $sucursalId) => $q->where('sucursal_id', $sucursalId))
            ->take(10)
            ->get();
    }

    /** Camino del autocompletado: llega un IMEI tecleado. */
    public function selectProducto($imei)
    {
        $producto = Producto::where('imei', $imei)->first();

        if ($producto) {
            $this->agregarProducto($producto);
        }
    }

    /**
     * Abre el selector de catalogo pasandole lo que ya esta en el carrito.
     *
     * Los ids van frescos en cada apertura, por eso el modal nunca tiene una
     * lista obsoleta. Tambien viajan la sucursal (para preseleccionar su
     * filtro) y el tipo de precio (para que la columna Precio no mienta).
     */
    public function openProductoSelector(): void
    {
        $this->dispatch(
            'openProductoSelectorModal',
            excluidos: collect($this->detalles)->pluck('producto_id')->map(fn($id) => (int) $id)->values()->all(),
            sucursalId: $this->sucursalIdParaBusqueda() ?? ($this->venta['sucursal_id'] ?? null),
            tipoPrecio: $this->tipo_precio,
        );
    }

    #[On('productosSeleccionados')]
    public function agregarProductos(array $ids): void
    {
        // Una sola consulta para toda la tanda, no una por id. El filtro de
        // estado repite aqui la comprobacion del modal: entre abrirlo y
        // confirmar, otro vendedor pudo llevarse uno de los equipos.
        $productos = Producto::whereIn('id', $ids)
            ->disponibles()
            ->with('compra')
            ->get();

        foreach ($productos as $producto) {
            $this->agregarProducto($producto);
        }
    }

    /**
     * Abre la eleccion de repuestos de reparacion de UN producto del carrito.
     *
     * Se le pasa lo ya elegido para que reabrir el modal no pierda los precios
     * que el vendedor habia escrito.
     */
    public function abrirRepuestosDe($productoId): void
    {
        $this->dispatch(
            'openProductoRepuestosVentaModal',
            productoId: (int) $productoId,
            yaElegidos: $this->repuestosVenta[$productoId] ?? [],
        );
    }

    #[On('repuestosDeReparacionSeleccionados')]
    public function guardarRepuestosDe($productoId, array $lineas): void
    {
        // La seleccion se guarda POR PRODUCTO: con varios telefonos en la
        // venta, lo elegido para uno no puede pisar lo del otro.
        if (empty($lineas)) {
            unset($this->repuestosVenta[$productoId]);
            return;
        }

        $this->repuestosVenta[$productoId] = $lineas;
    }

    /** Lo que suman los repuestos marcados, en todos los productos. */
    public function totalRepuestosVenta(): float
    {
        $total = 0;

        foreach ($this->repuestosVenta as $lineas) {
            foreach ($lineas as $linea) {
                $total += (float) ($linea['subtotal'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /** El cuerpo compartido: dedupe, siembra de la tasa y armado de la linea. */
    protected function agregarProducto(Producto $producto): void
    {
        foreach ($this->detalles as $detalle) {
            if ($detalle['producto_id'] === $producto->id) return;
        }

        // Solo el primer producto fija la tasa. Antes CADA producto agregado
        // pisaba la de la venta con la de su lote de compra, asi que sumar un
        // telefono de otra compra re-cotizaba en silencio una venta ya armada.
        // El campo sigue siendo editable a mano.
        if ($this->debeSembrarTipoCambio() && $producto->compra) {
            $this->venta['tipo_cambio'] = $producto->compra->tipo_cambio;
        }

        array_unshift($this->detalles, [
            'producto_id' => $producto->id,
            'descripcion' => $producto->descripcion,
            'imei' => $producto->imei,
            'costo' => $producto->costo_total,
            'precio' => $this->precioSegunTipo($producto),
            'descuento' => 0,
            'subtotal' => 0,
            'garantia_meses' => 3,
            'garantia_fecha_exp' => now()->addMonths(3)->format('Y-m-d'),
            'is_existing' => false,
            // Se cuenta una vez, al agregar, y se guarda en la linea: pintar
            // el boton de repuestos preguntandolo en cada render seria una
            // consulta por fila en cada peticion.
            'repuestos_elegibles' => app(RepuestosDeReparacionService::class)->contarElegibles($producto->id),
        ]);

        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->recalcularSubtotales();
    }

    public function eliminarDetalle($index)
    {
        if (!isset($this->detalles[$index]) || !$this->lineaEditable($this->detalles[$index])) {
            return;
        }

        // Si el telefono sale del carrito, sus repuestos se van con el: cobrar
        // la bateria de un equipo que ya no se vende no tiene sentido.
        unset($this->repuestosVenta[$this->detalles[$index]['producto_id']]);

        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
        $this->recalcularSubtotales();
    }

    public function updatedDetalles()
    {
        $this->recalcularSubtotales();
    }

    public function updatedVenta()
    {
        $this->recalcularSubtotales();
    }

    /**
     * Re-precia las lineas que aun se pueden tocar.
     *
     * Antes no existia: cambiar de Vendedor a Cliente solo afectaba a los
     * productos que se agregaran despues, y los ya cargados se quedaban con el
     * precio del otro tipo sin ningun aviso.
     */
    public function updatedTipoPrecio()
    {
        $ids = collect($this->detalles)
            ->filter(fn($detalle) => $this->lineaEditable($detalle))
            ->pluck('producto_id');

        if ($ids->isEmpty()) {
            return;
        }

        // Una sola consulta para todas las lineas, no una por fila.
        $productos = Producto::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($this->detalles as $index => $detalle) {
            $producto = $productos->get($detalle['producto_id']);

            if ($producto && $this->lineaEditable($detalle)) {
                $this->detalles[$index]['precio'] = $this->precioSegunTipo($producto);
            }
        }

        $this->recalcularSubtotales();
    }

    protected function precioSegunTipo(Producto $producto)
    {
        return $this->tipo_precio == 'Vendedor'
            ? $producto->precio_vendedor
            : $producto->precio_cliente;
    }

    public function recalcularSubtotales()
    {
        foreach ($this->detalles as $index => $detalle) {
            $precio = $detalle['precio'] ?? 0;
            $descuento = $detalle['descuento'] ?? 0;
            $this->detalles[$index]['subtotal'] = $precio - $descuento;
        }

        $this->venta['subtotal'] = round(collect($this->detalles)->sum('subtotal'), 2);
        $this->venta['total'] = round($this->venta['subtotal'] - ($this->venta['descuento'] ?? 0), 2);
        $this->venta['total_bs'] = round($this->venta['total'] * ($this->venta['tipo_cambio'] ?? 0), 2);
    }

    /*
     * El bloqueo al guardar ya no vive aqui.
     *
     * Era bloquearProductoDisponible(): releia el producto con lockForUpdate y
     * confirmaba que seguia vendible. Se mudo a
     * EstadoProductoService::vender(), que hace lo mismo y ademas escribe el
     * estado y su fila de historial de una pieza -- porque el modal de estado,
     * que es la TERCERA puerta de venta, no llamaba a este helper y vendia sin
     * ninguna comprobacion. Un helper que solo usan dos de los tres caminos no
     * es una garantia.
     */
}
