<?php

namespace App\Livewire\VentaRepuesto;

use App\Models\Cliente;
use App\Models\VentaRepuesto;
use App\Models\VentaRepuestoDetalle;
use App\Models\Repuesto;
use App\Services\StockRepuestoService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\RepuestoBuscadorTrait;
use App\Traits\VentaRepuestoTotalBsTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class VentaRepuestoEdit extends Component
{
    use ClienteBuscadorTrait;
    use RepuestoBuscadorTrait;
    use VentaRepuestoTotalBsTrait;

    public $ventaRepuesto;
    public $ventaRepuestoModel;
    public $detalles = []; 
    public $detallesOriginales = [];
    public $detallesEliminados = [];

    public $confirmingUpdate = false;

    protected function rules()
    {
        return [
            'detalles' => 'required|array|min:1',
            'detalles.*.precio' => 'required|numeric|min:0',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'detalles.*.descuento' => 'required|numeric|min:0',
            'ventaRepuesto.cliente' => 'nullable|string',
            'ventaRepuesto.sucursal_id' => 'required',
            'ventaRepuesto.total' => 'required|numeric|min:0',
            'ventaRepuesto.cantidad_repuestos' => 'required|numeric|min:1',
            'ventaRepuesto.descuento' => 'required|numeric|min:0',
            'ventaRepuesto.mano_obra' => 'nullable|numeric|min:0',
            'ventaRepuesto.tipo_cambio' => 'required|numeric|min:1',
            'ventaRepuesto.total_bs' => 'required|numeric|min:0',
            // Sin tope: puede ser negativo (se redondeo hacia abajo al cobrar).
            // El min:0 de total_bs atrapa un ajuste negativo desmedido.
            'ventaRepuesto.ajuste_bs' => 'nullable|numeric',
        ];
    }

    protected $messages = [
        'detalles.required' => 'La venta debe tener al menos un repuesto.',
        'detalles.min' => 'La venta debe tener al menos un repuesto.',
        'detalles.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
        'ventaRepuesto.sucursal_id.required' => 'Por favor, seleccione una sucursal.',
        'ventaRepuesto.descuento.min' => 'El descuento debe ser mayor o igual a 0.',
        'ventaRepuesto.tipo_cambio.min' => 'El tipo de cambio debe ser mayor o igual a 1.',
        'ventaRepuesto.total_bs.required' => 'Por favor, ingrese el total en bolivianos.',
        'ventaRepuesto.total_bs.numeric' => 'El total en bolivianos debe ser un numero.',
        'ventaRepuesto.total_bs.min' => 'El total en bolivianos no puede ser negativo.',
    ];

    public function mount($ventaRepuesto)
    {
        $venta = VentaRepuesto::with('detalles.repuesto.modelo')->findOrFail($ventaRepuesto->id);
        $this->ventaRepuestoModel = $venta;
        $this->ventaRepuesto = $venta->toArray();

        $this->ventaRepuesto['created_at'] = Carbon::parse($venta->created_at)->format('Y-m-d');

        foreach ($venta->detalles as $detalle) {
            $this->detalles[] = [
                'id' => $detalle->id,
                'repuesto_id' => $detalle->repuesto->id,
                'nombre' => $detalle->repuesto->nombre,
                'fabricante' => $detalle->repuesto->fabricante,
                // Sin `?? 'Sin modelo'`: ver x-articulo-etiqueta.
                'modelo' => $detalle->repuesto->modelo?->nombre,
                'costo' => $detalle->costo,
                'precio' => $detalle->precio,
                'cantidad' => $detalle->cantidad,
                'descuento' => $detalle->descuento,
                'subtotal_costo' => $detalle->subtotal_costo,
                'subtotal' => $detalle->subtotal,
                // Viene de un repuesto montado en una reparacion: su stock ya
                // salio del almacen entonces y aqui no se mueve nunca.
                'stock_ya_descontado' => $detalle->stockYaDescontado(),
            ];
        }

        $this->detallesOriginales = $this->detalles;

        // Ya no hace falta rescatar el total_bs guardado a mano: el ajuste lo
        // reconstruye solo. Ese apano existia justamente porque la diferencia
        // entre lo cobrado y la conversion no tenia donde guardarse.
        $this->recalcularTotales();
    }

    /**
     * Guarda la ficha elegida en la cabecera.
     *
     * Escribe las DOS claves: `cliente_id` es el enlace, y `cliente` es el nombre
     * que queda CONGELADO en el documento -- el archivo de a quien se le vendio,
     * que no se reescribe si manana le corrigen el nombre a la ficha.
     */
    protected function fijarCliente(?Cliente $cliente): void
    {
        $this->ventaRepuesto['cliente_id'] = $cliente?->id;
        $this->ventaRepuesto['cliente'] = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return isset($this->ventaRepuesto['cliente_id']) ? (int) $this->ventaRepuesto['cliente_id'] : null;
    }

    public function render()
    {
        return view('livewire.venta-repuesto.venta-repuesto-edit');
    }

    /**
     * La sucursal desde la que se vende. La lee RepuestoBuscadorTrait.
     */
    public function sucursalDelDocumento(): ?int
    {
        return isset($this->ventaRepuesto['sucursal_id']) && $this->ventaRepuesto['sucursal_id'] !== null
            ? (int) $this->ventaRepuesto['sucursal_id']
            : null;
    }

    /** Es una venta: un articulo sin stock en esa sucursal no se puede elegir. */
    public function exigeStockParaElegir(): bool
    {
        return true;
    }

    /** El aviso del buscador apagado y el toast del servidor, en un solo sitio. */
    public function motivoSinSucursal(): string
    {
        return 'Esta venta no tiene sucursal: no se le pueden agregar articulos.';
    }

    public function selectRepuesto($id)
    {
        // Faltaba: las dos pantallas de crear si lo comprobaban y esta no, asi
        // que una venta antigua sin sucursal aceptaba lineas y metia un null en
        // StockRepuestoService. Aqui la sucursal es de solo lectura, asi que el
        // mensaje no pide elegirla -- dice que el documento no se puede tocar.
        if (!$this->puedeElegirArticulos()) {
            toastr()->warning($this->motivoSinSucursal());
            return;
        }

        $repuesto = Repuesto::find($id);
        if (!$repuesto) return;

        foreach ($this->detalles as $detalle) {
            if ((int) $detalle['repuesto_id'] === (int) $repuesto->id) return;
        }

        array_unshift($this->detalles, [
            'id' => null,
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'fabricante' => $repuesto->fabricante,
            'modelo' => $repuesto->modelo?->nombre,
            'costo' => $repuesto->costo,
            'precio' => $repuesto->precio,
            'cantidad' => 1,
            'descuento' => 0,
            'subtotal_costo' => $repuesto->costo,
            'subtotal' => $repuesto->precio,
        ]);

        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];
        $this->recalcularTotales();
    }

    public function updatedDetalles()
    {
        $this->recalcularTotales();
    }

    public function updatedVentaRepuesto($value = null, $key = null)
    {
        // Livewire pasa como $key la subclave tocada ('total_bs', 'descuento'...).
        // El Bs tecleado no se recalcula: define el ajuste. Ver el trait.
        if ($key === 'total_bs') {
            $this->aplicarTotalBs($value);
            return;
        }

        $this->recalcularTotales();
    }

    public function recalcularTotales()
    {
        $costoTotalVenta = 0;
        $subtotalVenta = 0;

        foreach ($this->detalles as $index => $detalle) {
            $costo = is_numeric($detalle['costo']) ? (float)$detalle['costo'] : 0;
            $precio = is_numeric($detalle['precio']) ? (float)$detalle['precio'] : 0;
            $cantidad = is_numeric($detalle['cantidad']) ? (int)$detalle['cantidad'] : 0;
            // Clamp y seguir (antes hacia `return`, abortando el recalculo
            // completo: teclear 0 en una cantidad borraba la mano de obra del
            // total en silencio).
            if ($cantidad <= 0) {
                $cantidad = 1;
                $this->detalles[$index]['cantidad'] = 1;
            }

            // Una linea cobrada con el equipo se cobra por lo que se monto: se
            // devuelve a su cantidad original antes de calcular nada, para que
            // el subtotal no quede armado sobre un valor manipulado.
            if ($this->lineaBloqueada($detalle)) {
                $original = collect($this->detallesOriginales)->firstWhere('id', $detalle['id'] ?? null);

                if ($original) {
                    $cantidad = (int) $original['cantidad'];
                    $this->detalles[$index]['cantidad'] = $cantidad;
                }
            }

            $descuentoDetalle = is_numeric($detalle['descuento']) ? (float)$detalle['descuento'] : 0;

            $subtotalDetalle = ($precio * $cantidad) - $descuentoDetalle;
            $subtotalCostoDetalle = $costo * $cantidad;

            $this->detalles[$index]['subtotal'] = $subtotalDetalle;
            $this->detalles[$index]['subtotal_costo'] = $subtotalCostoDetalle;

            $costoTotalVenta += $subtotalCostoDetalle;
            $subtotalVenta += $subtotalDetalle;
        }

        $manoObra = is_numeric($this->ventaRepuesto['mano_obra'] ?? 0) ? (float) $this->ventaRepuesto['mano_obra'] : 0;

        // La mano de obra suma al total Y al costo: se paga a un tercero, asi
        // que se cancela en ganancia = total - costo_total.
        $this->ventaRepuesto['costo_total'] = round($costoTotalVenta + $manoObra, 2);
        $this->ventaRepuesto['subtotal'] = round($subtotalVenta, 2);
        $descuentoVenta = is_numeric($this->ventaRepuesto['descuento']) ? (float)$this->ventaRepuesto['descuento'] : 0;
        $this->ventaRepuesto['total'] = round($subtotalVenta - $descuentoVenta + $manoObra, 2);
        // base + ajuste: mover la tasa o agregar un repuesto rehace la base y
        // conserva el recargo que ya se habia pactado.
        $this->sincronizarTotalBs();
        $this->ventaRepuesto['cantidad_repuestos'] = count($this->detalles);
    }

    /**
     * Una linea que nace de una reparacion no se quita desde aqui.
     *
     * Ocultar el boton no es una defensa: la comprobacion tiene que estar en el
     * servidor. Para descobrarla hay que cancelar la venta del equipo.
     */
    protected function lineaBloqueada($detalle): bool
    {
        return (bool) ($detalle['stock_ya_descontado'] ?? false);
    }

    public function eliminarDetalle($index)
    {
        if (isset($this->detalles[$index]) && $this->lineaBloqueada($this->detalles[$index])) {
            toastr()->warning('Ese repuesto se cobró con la venta del equipo. Cancela esa venta para descobrarlo.');
            return;
        }

        if (isset($this->detalles[$index]['id']) && !is_null($this->detalles[$index]['id'])) {
            $this->detallesEliminados[] = $this->detalles[$index]['id'];
        }
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);
        $this->recalcularTotales();
    }

    public function confirmUpdate()
    {
        $this->validate();
        $this->confirmingUpdate = true;
    }

    public function update()
    {
        $this->validate();

        $stock = new StockRepuestoService();

        DB::transaction(function () use ($stock) {
            $venta = VentaRepuesto::findOrFail($this->ventaRepuesto['id']);

            // 1. Devolver stock de detalles eliminados.
            if (!empty($this->detallesEliminados)) {
                $detallesABorrar = VentaRepuestoDetalle::whereIn('id', $this->detallesEliminados)->get();

                // UNA sola coleccion filtrada para las dos decisiones: devolver
                // stock y borrar la fila. Antes eran dos filtros separados que
                // tenian que coincidir -- un reject() que reasignaba
                // $detallesEliminados y un continue dentro del bucle -- y
                // colapsar el bucle sin mirar el reject rompia la guarda.
                //
                // Una linea que viene de una reparacion nunca descontó stock
                // aqui (la pieza salio del almacen cuando el tecnico la monto),
                // asi que devolverla regalaria una unidad que nunca se debio; y
                // tampoco se borra desde aqui: se descobra cancelando la venta
                // del equipo.
                $devolubles = $detallesABorrar->reject->stockYaDescontado();

                // Ordenado por repuesto_id para no bloquear en InnoDB.
                foreach ($devolubles->sortBy('repuesto_id') as $detalle) {
                    // La sucursal DE LA LINEA, no la de la cabecera: es de donde
                    // salio la unidad.
                    $stock->ingresar($detalle->repuesto_id, $detalle->sucursal_id, (int) $detalle->cantidad);
                }

                $this->detallesEliminados = $devolubles->pluck('id')->all();
                VentaRepuestoDetalle::destroy($this->detallesEliminados);
            }

            // 2. Actualizar/Crear detalles y ajustar stock.
            // Ordenado por repuesto_id para no bloquear en InnoDB.
            foreach (collect($this->detalles)->sortBy('repuesto_id') as $detalleData) {
                $repuesto = Repuesto::find($detalleData['repuesto_id']);
                if (!$repuesto) continue;

                if (isset($detalleData['id']) && !is_null($detalleData['id'])) {
                    // Se relee la fila ANTES de decidir, y de ahi salen las dos
                    // cosas que antes venian de arrays publicos de Livewire:
                    //
                    //  - la guarda. lineaBloqueada() lee
                    //    $detalle['stock_ya_descontado'] de $this->detalles, sin
                    //    #[Locked]: un payload con ese valor en false hacia que
                    //    una linea de reparacion moviera stock que ya se movio.
                    //  - la cantidad "anterior", que salia de
                    //    $this->detallesOriginales: un payload con 999 ahi
                    //    devolvia 998 unidades que nunca existieron.
                    $detalleDB = VentaRepuestoDetalle::findOrFail($detalleData['id']);

                    // Las lineas que salen de una reparacion no mueven stock ni
                    // cambian de cantidad: lo que se cobra es lo que se monto en
                    // el telefono. El precio si se puede renegociar.
                    $bloqueada = $detalleDB->stockYaDescontado();
                    $cantidad = $bloqueada ? (int) $detalleDB->cantidad : (int) $detalleData['cantidad'];

                    if (!$bloqueada) {
                        // ajustarSalida y no una resta a mano: en una venta,
                        // subir la cantidad RETIRA mas stock. La direccion vive
                        // en el nombre del metodo, asi que no se puede copiar
                        // invertida del modulo de compras.
                        $stock->ajustarSalida(
                            $detalleDB->repuesto_id,
                            $detalleDB->sucursal_id,
                            (int) $detalleDB->cantidad,
                            $cantidad,
                        );
                    }

                    $detalleDB->update([
                        'precio' => $detalleData['precio'],
                        'cantidad' => $cantidad,
                        'descuento' => $detalleData['descuento'],
                        'subtotal' => $detalleData['subtotal'],
                        'subtotal_costo' => $detalleData['subtotal_costo'],
                        'tipo_cambio' => $this->ventaRepuesto['tipo_cambio'],
                        'subtotal_costo_bs' => $detalleData['subtotal_costo'] * $this->ventaRepuesto['tipo_cambio'],
                        'subtotal_bs' => $detalleData['subtotal'] * $this->ventaRepuesto['tipo_cambio'],
                    ]);
                } else {
                    // Detalle nuevo: Crear y descontar stock.
                    // La sucursal sale de la CABECERA GUARDADA y no del array
                    // publico: la de la venta no se cambia editando.
                    $sucursalId = $venta->sucursal_id;

                    VentaRepuestoDetalle::create([
                        'venta_repuesto_id' => $venta->id,
                        'repuesto_id' => $detalleData['repuesto_id'],
                        // Tipo congelado: se toma del catalogo en el momento de
                        // insertar la linea, no al leer el reporte.
                        'tipo' => $repuesto->tipo,
                        'sucursal_id' => $sucursalId,
                        'costo' => $detalleData['costo'],
                        'precio' => $detalleData['precio'],
                        'cantidad' => $detalleData['cantidad'],
                        'descuento' => $detalleData['descuento'],
                        'subtotal' => $detalleData['subtotal'],
                        'subtotal_costo' => $detalleData['subtotal_costo'],
                        'tipo_cambio' => $this->ventaRepuesto['tipo_cambio'],
                        'subtotal_costo_bs' => $detalleData['subtotal_costo'] * $this->ventaRepuesto['tipo_cambio'],
                        'subtotal_bs' => $detalleData['subtotal'] * $this->ventaRepuesto['tipo_cambio'],
                    ]);

                    $stock->retirar($repuesto->id, $sucursalId, (int) $detalleData['cantidad']);
                }
            }

            // sucursal_id NO viaja en este update() a proposito: la sucursal de
            // una venta no se cambia editando, y "solo lectura" tiene que ser
            // del servidor. Un `disabled` en el blade no manda el campo, pero
            // $this->ventaRepuesto es un array publico sin #[Locked] y un
            // payload manipulado lo cambiaria igual. Si no esta aqui, no hay
            // nada que manipular.
            //
            // De paso cierra un descuadre que ya existia: la cabecera se
            // reescribia pero las lineas guardadas conservaban su sucursal
            // vieja, asi que unidades salidas de una sucursal quedaban en una
            // venta marcada con otra.
            $venta->update([
                'cliente' => $this->ventaRepuesto['cliente'],
                'cliente_id' => $this->ventaRepuesto['cliente_id'] ?? null,
                'cantidad_repuestos' => $this->ventaRepuesto['cantidad_repuestos'],
                'subtotal' => $this->ventaRepuesto['subtotal'],
                'descuento' => $this->ventaRepuesto['descuento'],
                'mano_obra' => $this->ventaRepuesto['mano_obra'] ?? 0,
                'costo_total' => $this->ventaRepuesto['costo_total'],
                'total' => $this->ventaRepuesto['total'],
                'tipo_cambio' => $this->ventaRepuesto['tipo_cambio'],
                'total_bs' => $this->ventaRepuesto['total_bs'],
                'ajuste_bs' => $this->ajusteBs(),
                // El ajuste es cobro, no costo: el costo en Bs sigue saliendo
                // de la tasa a secas.
                'costo_total_bs' => $this->ventaRepuesto['costo_total'] * $this->ventaRepuesto['tipo_cambio'],
                'user_id' => Auth::id(),
            ]);

            // Una vez al final y no por movimiento: el mismo repuesto puede
            // moverse dos veces en un guardado (una linea borrada y otra nueva
            // del mismo articulo). Sin esta llamada el total cacheado se queda
            // atras respecto a la subtabla.
            $stock->recalcularTotales();
        });

        toastr()->success('Venta de repuesto actualizada exitosamente');
        return redirect()->route('ventas.repuestos.editar', $this->ventaRepuestoModel->id);
    }

}
