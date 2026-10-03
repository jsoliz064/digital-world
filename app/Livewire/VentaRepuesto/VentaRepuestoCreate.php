<?php

namespace App\Livewire\VentaRepuesto;

use App\Models\Cliente;
use App\Models\VentaRepuesto;
use App\Models\VentaRepuestoDetalle;
use App\Models\Repuesto;
use App\Models\Sucursal;
use App\Services\StockRepuestoService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\GuardadoIdempotenteTrait;
use App\Traits\RepuestoBuscadorTrait;
use App\Traits\VentaRepuestoTotalBsTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class VentaRepuestoCreate extends Component
{
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;
    use RepuestoBuscadorTrait;
    use VentaRepuestoTotalBsTrait;


    public $detalles = [];
    public $ventaRepuesto = [];

    /**
     * La sucursal que quedo fijada al cargar la primera linea.
     *
     * Publica porque tiene que sobrevivir entre requests (una privada vuelve a
     * null en cada una y el revert dejaria la sucursal vacia), y #[Locked] para
     * que el cliente no pueda reescribirla y saltarse el bloqueo.
     */
    #[Locked]
    public $sucursalFijada = null;
    public $confirmingStore = false;

    public function render()
    {
        return view('livewire.venta-repuesto.venta-repuesto-create', [
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    public function mount()
    {
        // Una clave por apertura de la pantalla: reintentar tras un corte de red
        // no registra la venta dos veces ni descuenta el stock dos veces.
        $this->nuevaClaveIdempotencia();

        $this->ventaRepuesto = [
            'created_at' => now()->format('Y-m-d'),
            'cantidad_repuestos' => 0,
            'subtotal' => 0,
            'descuento' => 0,
            'mano_obra' => 0,
            'costo_total' => 0,
            'total' => 0,
            'total_bs' => 0,
            'ajuste_bs' => 0,
            'tipo_cambio' => 0,
            'sucursal_id' => null,
            'cliente' => null,
            'cliente_id' => null,
        ];
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
        return 'Elige primero la sucursal de la venta.';
    }

    /**
     * La sucursal se elige UNA vez: es de donde sale el stock. Con lineas ya
     * cargadas, cambiarla significaria que las unidades salieron de un sitio y
     * la venta quedo registrada en otro.
     *
     * El bloqueo va aqui y no solo con un `disabled` en el blade: un disabled no
     * manda el campo, pero $this->ventaRepuesto es un array publico sin
     * #[Locked] y un payload manipulado lo cambia igual.
     */
    public function updatedVentaRepuestoSucursalId($value): void
    {
        if (empty($this->detalles)) {
            return;
        }

        $this->ventaRepuesto['sucursal_id'] = $this->sucursalFijada;
        toastr()->warning('Quita los repuestos cargados para poder cambiar de sucursal.');
    }

    public function selectRepuesto($id)
    {
        // La sucursal primero: define de donde sale el stock, y asi el stock que
        // se muestra en la linea es el de esa sucursal y no un total que no dice
        // nada de lo que hay para vender aqui.
        if (!$this->puedeElegirArticulos()) {
            toastr()->warning($this->motivoSinSucursal());
            return;
        }

        $repuesto = Repuesto::find($id);
        if (!$repuesto)
            return;

        foreach ($this->detalles as $detalle) {
            if ($detalle['repuesto_id'] === $repuesto->id)
                return;
        }

        $sucursalId = (int) $this->ventaRepuesto['sucursal_id'];

        array_unshift($this->detalles, [
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'fabricante' => $repuesto->fabricante,
            // Sin `?? 'Sin modelo'`: la ausencia se guarda como ausencia y la
            // pinta x-articulo-etiqueta, que omite las partes vacias. Un
            // accesorio no tiene modelo y el string inventado salia en pantalla.
            'modelo' => $repuesto->modelo?->nombre,
            'costo' => $repuesto->costo,
            'precio' => $repuesto->precio,
            'cantidad' => 1,
            'descuento' => 0,
            'subtotal_costo' => $repuesto->costo,
            'subtotal' => $repuesto->precio,
        ]);

        // Con la primera linea, la sucursal queda fijada.
        $this->sucursalFijada = $sucursalId;

        if ($this->ventaRepuesto['tipo_cambio'] == 0) {
            $this->ventaRepuesto['tipo_cambio'] = $repuesto->tipo_cambio;
        } else {
            $this->ventaRepuesto['tipo_cambio'] = ($this->ventaRepuesto['tipo_cambio'] + $repuesto->tipo_cambio) / 2;
        }

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
        foreach ($this->detalles as $index => $detalle) {
            $costo = $detalle['costo'] ?? 0;
            $precio = $detalle['precio'] ?? 0;
            $cantidad = $detalle['cantidad'] ?? 0;
            $descuento = $detalle['descuento'] ?? 0;
            $subtotal = $precio * $cantidad - $descuento;
            $this->detalles[$index]['subtotal_costo'] = $costo * $cantidad;
            $this->detalles[$index]['subtotal'] = $subtotal;
        }
        $manoObra = is_numeric($this->ventaRepuesto['mano_obra'] ?? 0) ? (float) $this->ventaRepuesto['mano_obra'] : 0;
        $descuentoVenta = is_numeric($this->ventaRepuesto['descuento'] ?? 0) ? (float) $this->ventaRepuesto['descuento'] : 0;

        // La mano de obra suma al total Y al costo: es dinero que se paga a un
        // tercero, asi que debe cancelarse en ganancia = total - costo_total.
        // Gracias a esto no hay que tocar VentaRepuestoIndex ni las tres
        // formulas de ganancia de ReporteIndex.
        $this->ventaRepuesto['costo_total'] = round(collect($this->detalles)->sum('subtotal_costo') + $manoObra, 2);
        $this->ventaRepuesto['subtotal'] = round(collect($this->detalles)->sum('subtotal'), 2);
        $this->ventaRepuesto['total'] = round($this->ventaRepuesto['subtotal'] - $descuentoVenta + $manoObra, 2);
        // base + ajuste: mover la tasa o agregar un repuesto rehace la base y
        // conserva el recargo que ya se habia pactado.
        $this->sincronizarTotalBs();
        $this->ventaRepuesto['cantidad_repuestos'] = sizeof($this->detalles);
    }

    public function eliminarDetalle($index)
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles);

        // Sin lineas, la sucursal vuelve a ser elegible.
        if (empty($this->detalles)) {
            $this->sucursalFijada = null;
        }

        $this->recalcularTotales();
    }

    protected $rules = [
        'detalles' => 'required|array|min:1',
        'ventaRepuesto.cliente' => 'nullable|string',
        'ventaRepuesto.sucursal_id' => 'required|exists:sucursales,id,activa,1',
        'ventaRepuesto.total' => 'required|numeric|min:1',
        'ventaRepuesto.cantidad_repuestos' => 'required|numeric|min:1',
        'ventaRepuesto.mano_obra' => 'nullable|numeric|min:0',
        // Faltaban: con el total en Bs editable, un 0 ahi dejaria el tipo de
        // cambio en 0 y la venta se guardaria con total_bs cero sin protestar.
        'ventaRepuesto.tipo_cambio' => 'required|numeric|min:1',
        'ventaRepuesto.total_bs' => 'required|numeric|min:0',
        // Sin tope: puede ser negativo (se redondeo hacia abajo al cobrar). El
        // min:0 de total_bs es lo que atrapa un ajuste negativo desmedido.
        'ventaRepuesto.ajuste_bs' => 'nullable|numeric',
    ];

    protected $messages = [
        'detalles.required' => 'Por favor, seleccione al menos un repuesto.',
        'detalles.min' => 'Por favor, seleccione al menos un repuesto.',
        'ventaRepuesto.sucursal_id.required' => 'Por favor, seleccione una sucursal.',
        'ventaRepuesto.sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
        'ventaRepuesto.total.required' => 'Por favor, ingrese un total.',
        'ventaRepuesto.total.min' => 'Por favor, el total debe ser mayor a 0.',
        'ventaRepuesto.cantidad_repuestos.required' => 'Por favor, ingrese la cantidad de repuestos.',
        'ventaRepuesto.cantidad_repuestos.min' => 'Por favor, la cantidad de repuestos debe ser mayor a 0.',
        'ventaRepuesto.mano_obra.numeric' => 'La mano de obra debe ser un numero.',
        'ventaRepuesto.mano_obra.min' => 'La mano de obra no puede ser negativa.',
        'ventaRepuesto.tipo_cambio.required' => 'Por favor, ingrese el tipo de cambio.',
        'ventaRepuesto.tipo_cambio.min' => 'El tipo de cambio debe ser mayor o igual a 1.',
        'ventaRepuesto.total_bs.required' => 'Por favor, ingrese el total en bolivianos.',
        'ventaRepuesto.total_bs.numeric' => 'El total en bolivianos debe ser un numero.',
        'ventaRepuesto.total_bs.min' => 'El total en bolivianos no puede ser negativo.',

    ];

    public function confirmStore()
    {
        $this->validate();
        $this->confirmingStore = true;
    }

    public function store()
    {
        $this->validate();

        // El reintento, resuelto antes de abrir la transaccion.
        if ($ya = $this->yaGuardado(VentaRepuesto::class)) {
            $this->avisarYaGuardado($ya, 'venta de repuestos');

            return redirect()->route('ventas.repuestos');
        }

        $stock = new StockRepuestoService();

        try {
            $ventaRepuesto = DB::transaction(function () use ($stock) {
                $user = Auth::user();

                $ventaRepuesto = VentaRepuesto::create($this->datosDeIdempotencia() + [
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
                    'costo_total_bs' => $this->ventaRepuesto['tipo_cambio'] * $this->ventaRepuesto['costo_total'],
                    'cliente' => $this->ventaRepuesto['cliente'],
                    'cliente_id' => $this->ventaRepuesto['cliente_id'] ?? null,
                    'sucursal_id' => $this->ventaRepuesto['sucursal_id'],
                    'user_id' => $user->id,
                ]);

                $sucursalId = (int) $this->ventaRepuesto['sucursal_id'];

                // Ordenado por repuesto_id: dos ventas simultaneas con las mismas
                // lineas en distinto orden se bloquean mutuamente en InnoDB.
                foreach (collect($this->detalles)->sortBy('repuesto_id') as $detalle) {
                    $repuesto = Repuesto::find($detalle['repuesto_id']);

                    VentaRepuestoDetalle::create([
                        'costo' => $detalle['costo'],
                        'precio' => $detalle['precio'],
                        'cantidad' => $detalle['cantidad'],
                        'descuento' => $detalle['descuento'],
                        'subtotal_costo' => $detalle['subtotal_costo'],
                        'subtotal' => $detalle['subtotal'],
                        'tipo_cambio' => $ventaRepuesto->tipo_cambio,
                        'subtotal_costo_bs' => $ventaRepuesto->tipo_cambio * $detalle['subtotal_costo'],
                        'subtotal_bs' => $ventaRepuesto->tipo_cambio * $detalle['subtotal'],
                        'repuesto_id' => $repuesto->id,
                        // Tipo congelado: reclasificar el articulo manana no debe
                        // reescribir los reportes de un periodo ya cerrado.
                        'tipo' => $repuesto->tipo,
                        'venta_repuesto_id' => $ventaRepuesto->id,
                        // La sucursal de la linea es el registro CONGELADO de donde
                        // salio esta unidad. Es lo que permite devolverla al sitio
                        // correcto al editar o anular la venta, aunque la cabecera
                        // diga otra cosa.
                        'sucursal_id' => $sucursalId,
                    ]);

                    // Si esa sucursal no tiene las unidades, retirar() lanza y la
                    // transaccion revierte la venta entera. Antes se podia vender 50
                    // de algo con stock 3 y el contador quedaba en -47.
                    $stock->retirar($repuesto->id, $sucursalId, (int) $detalle['cantidad']);
                }

                $stock->recalcularTotales();

                return $ventaRepuesto;
            });
        } catch (QueryException $e) {
            // Dos peticiones con la misma clave: la otra commiteo y el indice
            // nos rechazo. La transaccion revirtio entera, incluido el stock.
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(VentaRepuesto::class)) {
                $this->avisarYaGuardado($ya, 'venta de repuestos');

                return redirect()->route('ventas.repuestos');
            }

            throw $e;
        }

        toastr()->success('Venta de repuesto registrada exitosamente');
        $this->dispatch('refreshVentaRepuestoTable');
        // $this->closeModal();
        return redirect()->route('ventas.repuestos');
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

    public function closeModal()
    {
        $this->reset();
    }

}
