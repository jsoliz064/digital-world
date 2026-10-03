<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Bitacora;
use App\Models\ProductoModelo;
use App\Models\ProductoReparacion;
use App\Models\ProductoReparacionRepuesto;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use App\Models\Tecnicos;
use App\Models\Venta;
use App\Models\VentaProducto;
use App\Models\VentaRepuesto;
use App\Services\EstadoProductoService;
use App\Services\RepuestosDeReparacionService;
use App\Services\StockRepuestoService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ProductoEstadoModal extends Component
{
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;

    public $openModal = false;

    /**
     * #[Locked] en los dos: el id del producto y el estado del que partimos son
     * la identidad de la operacion. Sin esto, un payload podia pedir el cambio
     * sobre otro telefono o declarar un origen falso para saltarse la
     * precondicion de EstadoProductoService.
     *
     * OJO: #[Locked] NO protege las claves de los arrays de abajo
     * ($fueraTransito['id'], $vendido['venta_id'], $reparacion['id']). Esos se
     * revalidan contra producto_id en cada uso.
     */
    #[Locked]
    public $producto;

    /** El estado con el que se abrio el modal. La precondicion lo exige. */
    #[Locked]
    public $estadoOrigen;

    public $estado;
    public $fueraTransito = [];

    public $vendido = [];

    public $reparacion = [];
    public $tecnico;
    public $tecnicos;
    public $precioSeleccionado = null;

    public $searchRepuesto = '';
    public $categoriaId = null;
    public $modeloId = null;
    public $categorias;
    public $modelos;
    public $filteredRepuestos = [];

    public $repuestos = [];

    /**
     * La sucursal de donde salen las piezas que se montan en esta reparacion.
     *
     * La elige el tecnico y se CONGELA en cada linea
     * (productos_reparaciones_repuestos.sucursal_id). No se deduce de
     * $producto->sucursal_id, y el motivo es concreto: al terminar la reparacion
     * el equipo se reasigna al Almacen, en otra request. Si manana se quita esta
     * linea y el stock volviera a la sucursal ACTUAL del producto, iria al
     * Almacen y la sucursal real quedaria en negativo para siempre.
     */
    public $sucursalRepuestos = null;

    /**
     * Repuestos de reparaciones previas que se cobran junto al telefono.
     *
     *   id de linea de reparacion => precio editado
     *
     * Solo se usa en la rama Vendido.
     */
    public $repuestosCobrar = [];
    public $preciosCobrar = [];

    public function render(RepuestosDeReparacionService $servicio)
    {
        $productoEstados = ProductoEstado::toSelectArrayPermission();

        // Solo se ofrecen al vender, y solo mientras la venta no exista: una
        // vez registrada, esta pantalla es de solo lectura.
        $repuestosElegibles = collect();

        // Y su gemela: con la venta ya hecha se muestra lo que SE cobro.
        $ventasRepuestosCobradas = collect();

        if ($this->openModal && !empty($this->vendido['venta_id'])) {
            $ventasRepuestosCobradas = VentaRepuesto::with([
                'detalles.repuesto',
                'detalles.reparacionRepuesto',
            ])->where('venta_id', $this->vendido['venta_id'])->get();
        }

        if (
            $this->openModal && $this->producto
            && $this->estado === ProductoEstado::Vendido->value
            && $this->producto->estado !== ProductoEstado::Vendido->value
        ) {
            $repuestosElegibles = $servicio->elegibles($this->producto->id);

            foreach ($repuestosElegibles as $linea) {
                // Precio del catalogo, editable antes de confirmar.
                $this->preciosCobrar[(string) $linea->id] ??= $linea->repuesto?->precio ?? 0;
            }
        }

        // En render(): es variable de vista, asi que no viaja en el payload de
        // Livewire ni la vacia un reset().
        $sucursales = Sucursal::activas()->orderBy('nombre')->get();

        return view(
            'livewire.producto.modals.producto-estado-modal',
            compact('productoEstados', 'repuestosElegibles', 'ventasRepuestosCobradas', 'sucursales')
        );
    }

    #[On('openProductoEstadoModal')]
    public function openModal($id)
    {
        $producto = Producto::find($id);
        $this->categorias = RepuestoCategoria::all();
        $this->modelos = ProductoModelo::all();
        $this->tecnicos = Tecnicos::all();
        $this->producto = $producto;
        $this->openModal = true;
        $this->estado = $producto->estado;

        // El estado de partida se congela al abrir: es contra este que se
        // compara al guardar, no contra el que haya en la base en ese momento.
        $this->estadoOrigen = $producto->estado;

        // Una clave por apertura del modal: la rama Vendido crea una Venta, y
        // reintentarla no debe crear la segunda.
        $this->nuevaClaveIdempotencia();

        if ($producto->estado == ProductoEstado::Fuera->value || $producto->estado == ProductoEstado::Transito->value) {
            // Solo el texto, ya no el id de la fila: antes se guardaba el id para
            // SOBRESCRIBIR esa fila al guardar, y la bitacora es inmutable.
            $this->fueraTransito = ['descripcion' => $this->notaVigente()?->descripcion];
        }

        if ($producto->estado == ProductoEstado::Vendido->value) {
            $ventaProducto = $producto->ventaProducto;
            $this->vendido = $ventaProducto ? $ventaProducto->toArray() : $this->initialVendido();
            // El nombre a MOSTRAR sale de nombreCliente(): la ficha si la hay, el
            // texto congelado si la venta es anterior al modulo de clientes.
            $this->vendido['cliente'] = $ventaProducto ? $ventaProducto->venta->nombreCliente() : null;
            $this->vendido['cliente_id'] = $ventaProducto ? $ventaProducto->venta->cliente_id : null;
        }

        if ($producto->estado == ProductoEstado::Reparacion->value) {
            $productoReparacion = $producto->ultimaReparacion();
            $this->tecnico = $productoReparacion ? $productoReparacion->tecnico : null;
            $this->reparacion = $productoReparacion ? $productoReparacion->toArray() : $this->initialReparacion();
            $this->reparacion['pagado'] = (bool) $this->reparacion['pagado'];
            $this->reparacion['garantia_tecnico'] = (bool) $this->reparacion['garantia_tecnico'];
        }
    }

    /**
     * Guarda la ficha elegida en la cabecera de la venta que este modal creara.
     *
     * Escribe las DOS claves: `cliente_id` es el enlace y `cliente` el nombre que
     * queda congelado en el documento.
     */
    protected function fijarCliente(?Cliente $cliente): void
    {
        $this->vendido['cliente_id'] = $cliente?->id;
        $this->vendido['cliente'] = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return isset($this->vendido['cliente_id']) ? (int) $this->vendido['cliente_id'] : null;
    }

    public function initialVendido()
    {
        return [
            'cliente' => null,
            'cliente_id' => null,
            'garantia_meses' => 3,
            'garantia_fecha_exp' => now()->addMonths(3)->format('Y-m-d'),
            'descuento' => 0,
            'precio' => 0,
            'subtotal' => 0,
            'tipo_cambio' => $this->producto->compra->tipo_cambio,
            'subtotal_bs' => 0
        ];
    }

    public function seleccionarPrecio($precio)
    {
        $this->precioSeleccionado = $precio;
        $this->vendido['precio'] = $precio;
        $this->vendido['subtotal'] = $precio;
        $this->vendido['subtotal_bs'] = $precio * $this->vendido['tipo_cambio'];
    }

    public function updatedEstado()
    {
        if ($this->estado == 'Vendido') {
            $this->vendido = $this->initialVendido();
        }

        if ($this->estado == 'Reparacion') {
            $this->reparacion = $this->initialReparacion();
        }
    }

    public function updatedVendidoPrecio()
    {
        $this->calcularSubTotalVendido();
    }

    public function updatedVendidoDescuento()
    {
        $this->calcularSubTotalVendido();
    }

    public function updatedVendidoTipoCambio()
    {
        $this->calcularSubTotalVendido();
    }

    public function updatedVendidoGarantiaMeses()
    {
        $meses = (int) $this->vendido['garantia_meses'];
        if ($meses < 0) {
            $meses = 0;
            $this->vendido['garantia_meses'] = 0;
        }
        $this->vendido['garantia_fecha_exp'] = now()->addMonths($meses)->format('Y-m-d');
    }

    public function calcularSubTotalVendido()
    {
        $precio = floatval($this->vendido['precio'] ?? 0);
        $descuento = floatval($this->vendido['descuento'] ?? 0);
        $this->vendido['subtotal'] = max($precio - $descuento, 0);
        $this->vendido['subtotal_bs'] = $this->vendido['subtotal'] * $this->vendido['tipo_cambio'];
    }

    // REPARACION

    public function initialReparacion()
    {
        return [
            'costo' => 0,
            'costo_repuestos' => 0,
            'tipo_cambio' => $this->producto->compra->tipo_cambio,
            'costo_total' => 0,
            'costo_total_bs' => 0,
            'fecha_entrega' => now()->format('Y-m-d'),
            'pagado' => false,
            'garantia_tecnico' => false,
        ];
    }

    public function updatedCategoriaId()
    {
        if ($this->categoriaId == "") {
            $this->categoriaId = null;
        }
        $this->filterRepuestos($this->searchRepuesto);
    }

    public function updatedModeloId()
    {
        if ($this->modeloId == "") {
            $this->modeloId = null;
        }
        $this->filterRepuestos($this->searchRepuesto);
    }

    public function updatedSearchRepuesto($value)
    {
        if (empty($value)) {
            $this->filteredRepuestos = [];
            return;
        }

        $this->filterRepuestos($value);
    }

    private function filterRepuestos($search)
    {
        $idsExistentes = collect($this->repuestos)->pluck('repuesto_id')->toArray();

        // Un accesorio no se monta en una reparacion: el filtro es
        // incondicional y vive en un scope para que el proximo buscador que
        // alguien escriba no se olvide de el.
        $this->filteredRepuestos = Repuesto::soloRepuestos()->where(function ($query) use ($search) {
            $query->where('nombre', 'like', '%' . $search . '%')
                ->orWhere('fabricante', 'like', '%' . $search . '%')
                ->orWhereHas('modelo', function ($queryModelo) use ($search) {
                    $queryModelo->where('nombre', 'like', '%' . $search . '%');
                });
        })
            ->whereNotIn('id', $idsExistentes)
            ->when($this->categoriaId, function ($query) {
                $query->where('repuesto_categoria_id', $this->categoriaId);
            })
            ->when($this->modeloId, function ($query) {
                $query->where('producto_modelo_id', $this->modeloId);
            })
            ->orderBy('nombre')
            ->take(20)
            ->get();
    }

    public function selectRepuesto($id)
    {
        $repuesto = Repuesto::find($id);
        if (!$repuesto)
            return;

        array_unshift($this->repuestos, [
            'id' => null,
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'modelo' => $repuesto->modelo ? $repuesto->modelo->nombre : '',
            'fabricante' => $repuesto->fabricante,
            'costo' => $repuesto->costo,
            'cantidad' => 1,
            'subtotal_costo' => $repuesto->costo,
            'subtotal_costo_bs' => $repuesto->costo * $this->reparacion['tipo_cambio'],
        ]);

        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];
        $this->calcularTotalRepuestos();
    }

    public function updatedRepuestos()
    {
        $this->calcularTotalRepuestos();
    }

    public function updatedReparacionCosto()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCostoRepuestos()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionTipoCambio()
    {
        $this->calcularTotalReparacion();
    }

    public function calcularTotalRepuestos()
    {
        $costoTotalRepuestosBs = 0;

        foreach ($this->repuestos as $index => $repuesto) {
            $costo = is_numeric($repuesto['costo']) ? (float) $repuesto['costo'] : 0;
            $cantidad = is_numeric($repuesto['cantidad']) ? (int) $repuesto['cantidad'] : 0;
            if ($cantidad <= 0) {
                $this->repuestos[$index]['cantidad'] = 1;
                return;
            }

            $subtotalCostoRepuesto = $costo * $cantidad;
            $this->repuestos[$index]['subtotal_costo'] = $subtotalCostoRepuesto;
            $subtotalCostoRepuestoBs = $subtotalCostoRepuesto * $this->reparacion['tipo_cambio'];
            $this->repuestos[$index]['subtotal_costo_bs'] = $subtotalCostoRepuestoBs;
            $costoTotalRepuestosBs += $subtotalCostoRepuestoBs;
        }
        $this->reparacion['costo_repuestos'] = $costoTotalRepuestosBs;
        $this->calcularTotalReparacion();
    }

    public function calcularTotalReparacion()
    {
        $costo = floatval($this->reparacion['costo'] ?? 0);
        $costo_repuestos = floatval($this->reparacion['costo_repuestos'] ?? 0);
        $tipo_cambio = floatval($this->reparacion['tipo_cambio'] ?? 0);
        $costo_usd = $tipo_cambio > 0 ? $costo / $tipo_cambio : 0;
        $costo_repuestos_usd = $tipo_cambio > 0 ? $costo_repuestos / $tipo_cambio : 0;
        $costo_total = $costo_usd + $costo_repuestos_usd;
        $this->reparacion['costo_total'] = $costo_total;
        $this->reparacion['costo_total_bs'] = $costo + $costo_repuestos;
    }

    public function eliminarRepuesto($index)
    {
        unset($this->repuestos[$index]);
        $this->repuestos = array_values($this->repuestos);
        $this->calcularTotalRepuestos();
    }

    /**
     * Que el modal siga teniendo un producto con el que trabajar.
     *
     * Los cuatro metodos de guardado hacen reset() al terminar, que deja
     * $producto en null. Una segunda peticion sobre ese estado -- un boton que
     * quedo vivo, un reintento que llega tarde-- reventaba con "call to a
     * member function refresh() on null" y el usuario veia la pantalla de
     * error de Laravel en lugar de un aviso.
     */
    /**
     * La nota con la que el producto esta en Fuera o Transito: la ultima fila
     * de la bitacora con el evento de su estado actual.
     */
    protected function notaVigente(): ?Bitacora
    {
        if (!$this->producto) {
            return null;
        }

        return $this->producto->bitacoras()
            ->where('evento', $this->producto->estado)
            ->whereNotNull('descripcion')
            ->orderByDesc('id')
            ->first();
    }

    private function productoEnPantalla(): bool
    {
        if ($this->producto) {
            return true;
        }

        toastr()->info('Esta ventana ya se cerro. Vuelve a abrirla para ver como quedo el producto.');
        $this->dispatch('refreshProductoTable');

        return false;
    }

    public function update()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        $stock = new StockRepuestoService();
        $estados = app(EstadoProductoService::class);

        // Un cambio de estado de verdad, o solo una correccion de los datos del
        // estado en que ya esta: reabrir un producto en Fuera para arreglarle la
        // descripcion no mueve nada y no merece fila nueva de historial.
        $esTransicion = $this->estadoOrigen !== $this->estado;

        // El reintento de la unica rama que crea documento. Las otras las cubre
        // la precondicion del servicio, que al encontrar el producto ya en el
        // estado destino rechaza con un mensaje que lo nombra.
        if ($this->estado == ProductoEstado::Vendido->value && $ya = $this->yaGuardado(Venta::class)) {
            $this->avisarYaGuardado($ya, 'venta');
            $this->dispatch('refreshProductoTable');
            $this->reset();

            return;
        }

        try {
            DB::transaction(function () use ($stock, $estados, $esTransicion) {
                $user = Auth::user();

                // Las ramas de abajo preparan la nota del cambio; quien la escribe
                // es EstadoProductoService, al final y de una sola pieza con el
                // estado, para que no puedan decir cosas distintas.
                $descripcion = null;
                $enlaces = [];

                if ($this->estado == ProductoEstado::Fuera->value || $this->estado == ProductoEstado::Transito->value) {
                    $this->validate([
                        'fueraTransito.descripcion' => 'required',
                    ]);

                    // Antes esto hacia UPDATE sobre la fila de historial anterior,
                    // y el texto que habia se perdia para siempre. Ahora corregir la
                    // nota es un hecho mas, con su autor y su fecha.
                    //
                    // Se compara contra la nota de la BASE y no contra algo del
                    // payload: guardar el modal sin tocar la nota no debe dejar una
                    // fila repetida, y el payload no es de fiar para decidirlo.
                    $nueva = $this->fueraTransito['descripcion'];

                    if ($this->estadoOrigen !== $this->estado || $nueva !== $this->notaVigente()?->descripcion) {
                        $descripcion = $nueva;
                    }
                }

                if ($this->estado == ProductoEstado::Vendido->value) {
                    $this->validate([
                        'vendido.precio' => 'required',
                        'vendido.subtotal' => 'required',
                        'vendido.tipo_cambio' => 'required',
                        'vendido.subtotal_bs' => 'required',
                        'vendido.garantia_meses' => 'required',
                        'vendido.garantia_fecha_exp' => 'required',
                        'vendido.cliente' => 'nullable',
                    ]);

                    $cliente = isset($this->vendido['cliente']) ? $this->vendido['cliente'] : null;
                    $venta = Venta::create([
                        'subtotal' => $this->vendido['subtotal'],
                        'total' => $this->vendido['subtotal'],
                        'tipo_cambio' => $this->vendido['tipo_cambio'],
                        'total_bs' => $this->vendido['subtotal_bs'],
                        'cliente' => $cliente,
                        'cliente_id' => $this->vendido['cliente_id'] ?? null,
                        'user_id' => $user->id,
                        'sucursal_id' => $this->producto->sucursal_id
                    ] + $this->datosDeIdempotencia());

                    $ventaProducto = VentaProducto::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $this->producto->id,
                        'costo' => $this->producto->costo_total,
                        'precio' => $this->vendido['precio'],
                        'descuento' => $this->vendido['descuento'],
                        'subtotal' => $this->vendido['subtotal'],
                        'tipo_cambio' => $this->vendido['tipo_cambio'],
                        'subtotal_bs' => $this->vendido['subtotal_bs'],
                        'garantia_meses' => $this->vendido['garantia_meses'],
                        'garantia_fecha_exp' => $this->vendido['garantia_fecha_exp'],
                        'sucursal_id' => $this->producto->sucursal_id
                    ]);

                    $descripcion = "Producto Vendido. Venta: {$venta->id}, Cliente: {$venta->nombreCliente()}, Precio: $ {$ventaProducto->subtotal}";
                    $enlaces = ['venta_id' => $venta->id];

                    // Los repuestos montados en reparaciones previas que el
                    // vendedor decidio cobrar aparte. Dentro de la transaccion: si
                    // falla, la venta entera revierte.
                    $lineas = [];
                    foreach ($this->repuestosCobrar as $id) {
                        $lineas[] = [
                            'producto_reparacion_repuesto_id' => $id,
                            'precio' => $this->preciosCobrar[(string) $id] ?? 0,
                        ];
                    }
                    app(RepuestosDeReparacionService::class)->registrar($venta, $lineas, $user);

                    // Ya no se pisa disponible_catalogo: el catalogo excluye
                    // Vendido con un whereNotIn explicito y su condicion es
                    // `disponible_catalogo = 1 OR estado IN (Inventario, Oferta)`,
                    // asi que la bandera no cambiaba nada de lo que se ve. Lo que
                    // si hacia era borrar para siempre una casilla que el operador
                    // marca a mano en el modal del lote, sin guardar el valor
                    // anterior: al cancelar la venta no habia nada que restaurar.
                }

                if ($this->estado == ProductoEstado::Reparacion->value) {
                    $this->validate([
                        'reparacion.costo' => 'required|numeric|min:0',
                        'reparacion.costo_repuestos' => 'required|numeric|min:0',
                        'reparacion.tipo_cambio' => 'required|numeric|min:0',
                        'reparacion.costo_total' => 'required|numeric|min:0',
                        'reparacion.costo_total_bs' => 'required|numeric|min:0',
                        'reparacion.tecnico_id' => 'required',
                        'reparacion.fecha_entrega' => 'required',
                        'reparacion.garantia_tecnico' => 'required',
                        'reparacion.pagado' => 'required',
                    ]);

                    // Igual que el historial de Fuera: el id es del payload, asi
                    // que la reparacion tiene que ser de este producto.
                    $productoReparacion = isset($this->reparacion['id'])
                        ? ProductoReparacion::whereKey($this->reparacion['id'])
                            ->where('producto_id', $this->producto->id)
                            ->first()
                        : null;

                    if ($productoReparacion) {
                        $productoReparacion->update([
                            'tecnico_id' => $this->reparacion['tecnico_id'],
                            'costo' => $this->reparacion['costo'],
                            'costo_repuestos' => $this->reparacion['costo_repuestos'],
                            'tipo_cambio' => $this->reparacion['tipo_cambio'],
                            'costo_total' => $this->reparacion['costo_total'],
                            'costo_total_bs' => $this->reparacion['costo_total_bs'],
                            'repuestos_tecnico' => isset($this->reparacion['repuestos_tecnico']) ? $this->reparacion['repuestos_tecnico'] : null,
                            'repuestos_propios' => isset($this->reparacion['repuestos_propios']) ? $this->reparacion['repuestos_propios'] : null,
                            'repuestos_devolver' => isset($this->reparacion['repuestos_devolver']) ? $this->reparacion['repuestos_devolver'] : null,
                            'fecha_entrega' => $this->reparacion['fecha_entrega'],
                            'garantia_tecnico' => $this->reparacion['garantia_tecnico'],
                            'pagado' => $this->reparacion['pagado'],
                        ]);
                    } else {
                        $tecnico = Tecnicos::find($this->reparacion['tecnico_id']);

                        $productoReparacion = ProductoReparacion::create([
                            'tecnico_id' => $tecnico->id,
                            'producto_id' => $this->producto->id,
                            'costo' => $this->reparacion['costo'],
                            'costo_repuestos' => $this->reparacion['costo_repuestos'],
                            'tipo_cambio' => $this->reparacion['tipo_cambio'],
                            'costo_total' => $this->reparacion['costo_total'],
                            'costo_total_bs' => $this->reparacion['costo_total_bs'],
                            'repuestos_tecnico' => isset($this->reparacion['repuestos_tecnico']) ? $this->reparacion['repuestos_tecnico'] : null,
                            'repuestos_propios' => isset($this->reparacion['repuestos_propios']) ? $this->reparacion['repuestos_propios'] : null,
                            'repuestos_devolver' => isset($this->reparacion['repuestos_devolver']) ? $this->reparacion['repuestos_devolver'] : null,
                            'fecha_entrega' => $this->reparacion['fecha_entrega'],
                            'garantia_tecnico' => $this->reparacion['garantia_tecnico'],
                            'pagado' => $this->reparacion['pagado'],
                        ]);

                        // Ordenado por repuesto_id para no bloquear en InnoDB.
                        foreach (collect($this->repuestos)->sortBy('repuesto_id') as $reparacionRepuesto) {
                            $repuesto = Repuesto::find($reparacionRepuesto['repuesto_id']);

                            ProductoReparacionRepuesto::create([
                                'producto_reparacion_id' => $productoReparacion->id,
                                'repuesto_id' => $repuesto->id,
                                // Congelada: de aqui salio la pieza, y es donde
                                // volvera si manana se quita esta linea.
                                'sucursal_id' => $this->sucursalRepuestos,
                                'costo' => $reparacionRepuesto['costo'],
                                'cantidad' => $reparacionRepuesto['cantidad'],
                                'subtotal_costo' => $reparacionRepuesto['subtotal_costo'],
                                'subtotal_costo_bs' => $reparacionRepuesto['subtotal_costo_bs'],
                            ]);

                            $stock->retirar(
                                $repuesto->id,
                                $this->sucursalRepuestos,
                                (int) $reparacionRepuesto['cantidad'],
                            );
                        }

                        $stock->recalcularTotales();

                        $descripcion = "Producto en reparacion con el tecnico {$tecnico->nombre}";
                        $enlaces = ['producto_reparacion_id' => $productoReparacion->id];

                    }
                    $this->producto->refresh();
                    $this->producto->recalcularCosto();
                }

                if ($this->estado == ProductoEstado::Roto->value) {
                    $descripcion = "Producto roto";
                }

                // Inventario y Oferta comparten rama: son el mismo estado de cara a
                // la venta y los dos dejan su fila de historial.
                if (in_array($this->estado, ProductoEstado::disponibles(), true)) {
                    $descripcion = $this->estado === ProductoEstado::Oferta->value
                        ? 'Producto puesto en oferta'
                        : 'Producto movido a inventario';
                }

                if ($esTransicion) {
                    // Bloquea la fila, exige que siga en $estadoOrigen, comprueba el
                    // permiso del estado destino y escribe estado e historial de una
                    // pieza. Aqui si se exige el permiso: el usuario ELIGIO el estado
                    // de un select que ya esta filtrado por permisos, asi que la
                    // escritura tiene que coincidir con lo que ofrece ese select.
                    $estados->cambiar(
                        $this->producto->id,
                        ProductoEstado::from($this->estadoOrigen),
                        ProductoEstado::from($this->estado),
                        $descripcion ?? 'Cambio de estado',
                        $enlaces,
                        exigirPermiso: true,
                    );
                } elseif ($descripcion !== null) {
                    // No se mueve de estado pero queda nota: p. ej. un producto en
                    // Fuera sin fila previa que editar.
                    Bitacora::registrar($this->producto, $this->estado, $descripcion, $enlaces);
                }
            });
        } catch (QueryException $e) {
            // Dos peticiones con la misma clave: la otra commiteo y el indice
            // nos rechazo. Nuestra transaccion ya revirtio entera.
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Venta::class)) {
                $this->avisarYaGuardado($ya, 'venta');
                $this->dispatch('refreshProductoTable');
                $this->reset();

                return;
            }

            throw $e;
        }

        // Relectura: el estado lo escribio el servicio sobre su propia copia
        // bloqueada, asi que el modelo que tiene el componente quedo atras.
        $this->producto->refresh();

        $this->dispatch('refreshProductoTable');
        toastr()->success('Estado actualizado exitosamente');
        $this->reset();
    }

    public function finalizarReparacion()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        $estados = app(EstadoProductoService::class);

        DB::transaction(function () use ($estados) {
            // La reparacion tiene que ser de ESTE producto: el id llega en un
            // array publico y #[Locked] no protege sus claves.
            $productoReparacion = ProductoReparacion::whereKey($this->reparacion['id'] ?? null)
                ->where('producto_id', $this->producto->id)
                ->first();

            if (!$productoReparacion) {
                throw ValidationException::withMessages([
                    'detalles' => 'Esa reparacion no es de este producto. Recarga la pantalla.',
                ]);
            }

            $productoReparacion->update([
                'estado' => 'Terminado',
                'fecha_recogida' => now()->format('Y-m-d'),
            ]);

            // Precondicion: si otro ya lo saco de Reparacion, esto no se repite.
            $estados->cambiar(
                $this->producto->id,
                ProductoEstado::Reparacion,
                ProductoEstado::Inventario,
                "Reparacion finalizada con el tecnico {$productoReparacion->tecnico->nombre}",
                ['producto_reparacion_id' => $productoReparacion->id],
            );
        });

        $this->producto->refresh();

        $this->dispatch('refreshProductoTable');
        toastr()->success('Reparacion finalizada exitosamente');
        $this->reset();
    }

    public function finalizarFueraTransito()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        $estados = app(EstadoProductoService::class);

        DB::transaction(function () use ($estados) {
            // Dos origenes legitimos: el boton sale en los dos estados.
            $estados->cambiar(
                $this->producto->id,
                [ProductoEstado::Fuera, ProductoEstado::Transito],
                ProductoEstado::Inventario,
                'Producto entregado al inventario',
            );
        });

        $this->producto->refresh();

        $this->dispatch('refreshProductoTable');
        toastr()->success('Producto entregado exitosamente');
        $this->reset();
    }

    public function cancelarVenta()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        DB::transaction(function () {
            // La venta se deduce del PRODUCTO y no del payload: $vendido es un
            // array y #[Locked] no protege sus claves, asi que un venta_id
            // ajeno habria anulado la venta de otro cliente. ventaProducto() es
            // un hasOne y ahora lo respalda el indice unico de
            // ventas_productos.producto_id, asi que no hay ambiguedad.
            $detalle = $this->producto->ventaProducto;

            if (!$detalle) {
                throw ValidationException::withMessages([
                    'detalles' => 'Este producto no tiene una venta que anular. Recarga la pantalla.',
                ]);
            }

            // El mismo camino que usa VentaDetalleDestroyModal: descobrar los
            // repuestos, borrar la cabecera si era el ultimo producto y
            // devolver el equipo al inventario con su precondicion.
            app(AnulacionVentaService::class)->anularDetalle($detalle);
        });

        $this->producto->refresh();

        $this->dispatch('refreshProductoTable');
        toastr()->success('Venta cancelada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
