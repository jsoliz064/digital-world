<?php

namespace App\Livewire\CompraRepuesto;

use App\Models\CompraRepuesto;
use App\Models\CompraRepuestoDetalle;
use App\Models\Repuesto;
use App\Models\Sucursal;
use App\Services\StockRepuestoService;
use App\Traits\GuardadoIdempotenteTrait;
use App\Traits\RepuestoBuscadorTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class CompraRepuestoCreate extends Component
{
    use GuardadoIdempotenteTrait;
    use RepuestoBuscadorTrait;

    public $openModal = false;


    public $detalles = [];
    public $compraRepuesto = [];
    public $confirmingStore = false;

    /**
     * La sucursal que quedo fijada al cargar la primera linea.
     *
     * Publica porque tiene que sobrevivir entre requests (una propiedad privada
     * vuelve a null en cada una y el revert dejaria la sucursal vacia), y
     * #[Locked] para que el cliente no pueda reescribirla y saltarse el bloqueo.
     */
    #[Locked]
    public $sucursalFijada = null;

    public function mount()
    {
        // Una clave por apertura: reintentar no ingresa el stock dos veces.
        $this->nuevaClaveIdempotencia();

        // La sucursal arranca sin elegir y es obligatoria: el stock se lleva por
        // sucursal, asi que una compra sin destino no se puede registrar.
        $this->compraRepuesto['sucursal_id'] = null;
    }

    public function render()
    {
        // En render() y no en mount(): es variable de vista, no propiedad
        // publica, asi que no viaja en el payload de Livewire.
        return view('livewire.compra-repuesto.compra-repuesto-create', [
            'sucursales' => Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }

    /**
     * La sucursal se elige UNA vez. Con lineas ya cargadas, cambiarla
     * significaria que el stock entro en un sitio y se registro en otro.
     *
     * El bloqueo va aqui y no solo con un `disabled` en el blade: un disabled no
     * manda el campo, pero la propiedad publica persiste del lado del servidor y
     * un payload manipulado la cambia igual.
     */
    public function updatedCompraRepuestoSucursalId($value): void
    {
        if (empty($this->detalles)) {
            return;
        }

        $this->compraRepuesto['sucursal_id'] = $this->sucursalFijada;
        toastr()->warning('Quita los repuestos cargados para poder cambiar de sucursal.');
    }

    /**
     * La sucursal a la que entra la compra. La lee RepuestoBuscadorTrait.
     */
    public function sucursalDelDocumento(): ?int
    {
        return isset($this->compraRepuesto['sucursal_id']) && $this->compraRepuesto['sucursal_id'] !== null
            ? (int) $this->compraRepuesto['sucursal_id']
            : null;
    }

    /** El aviso del buscador apagado y el toast del servidor, en un solo sitio. */
    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal a la que entra la compra.';
    }

    public function selectRepuesto($id)
    {
        // La sucursal primero: fija el destino del stock, y asi el stock que se
        // muestra en la linea es el de esa sucursal y no un total que no dice
        // nada de donde va a entrar la mercaderia.
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

        $sucursalId = (int) $this->compraRepuesto['sucursal_id'];

        array_unshift($this->detalles, [
            'repuesto_id' => $repuesto->id,
            'nombre' => $repuesto->nombre,
            'fabricante' => $repuesto->fabricante,
            // Sin `?? 'Sin modelo'`: la ausencia se guarda como ausencia y la
            // pinta x-articulo-etiqueta, que omite las partes vacias. Un
            // accesorio no tiene modelo y el string inventado salia en pantalla.
            'modelo' => $repuesto->modelo?->nombre,
            'costo' => $repuesto->costo,
            'cantidad' => 1,
        ]);

        // Con la primera linea, la sucursal queda fijada.
        $this->sucursalFijada = $sucursalId;

        $this->searchRepuesto = '';
        $this->filteredRepuestos = [];
        $this->recalcularTotales();
    }

    public function updatedDetalles()
    {
        $this->recalcularTotales();
    }

    public function updatedCompraRepuesto()
    {
        $this->recalcularTotales();
    }

    public function recalcularTotales()
    {
        foreach ($this->detalles as $index => $detalle) {
            $costo = $detalle['costo'] ?? 0;
            $cantidad = $detalle['cantidad'] ?? 0;
            $subtotal = $costo * $cantidad;
            $this->detalles[$index]['subtotal'] = $subtotal;
        }

        $this->compraRepuesto['costo_total'] = round(collect($this->detalles)->sum('subtotal'), 2);
        $this->compraRepuesto['cantidad_repuestos'] = sizeof($this->detalles);
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

    /**
     * Metodo y no propiedad estatica (como era antes, y como ya es en Edit):
     * `exists` necesita consultar la base, y una propiedad no puede.
     */
    protected function rules()
    {
        return [
            'detalles' => 'required|array|min:1',
            'detalles.*.costo' => 'required|numeric|min:0',
            'detalles.*.cantidad' => 'required|integer|min:1',
            'compraRepuesto.sucursal_id' => 'required|integer|exists:sucursales,id,activa,1',
            'compraRepuesto.fecha_compra' => 'required|date',
            'compraRepuesto.costo_total' => 'required|numeric|min:1',
            'compraRepuesto.tipo_cambio' => 'required|numeric|min:1',
            'compraRepuesto.cantidad_repuestos' => 'required|numeric|min:1',
        ];
    }

    protected $messages = [
        'detalles.required' => 'Por favor, seleccione al menos un repuesto.',
        'detalles.min' => 'Por favor, seleccione al menos un repuesto.',
        'compraRepuesto.sucursal_id.required' => 'Por favor, seleccione la sucursal a la que entra la compra.',
        'compraRepuesto.fecha_compra.required' => 'Por favor, seleccione una fecha de compra.',
        'compraRepuesto.costo_total.required' => 'Por favor, ingrese un costo total.',
        'compraRepuesto.costo_total.min' => 'Por favor, el costo total debe ser mayor a 0.',
        'compraRepuesto.tipo_cambio.required' => 'Por favor, ingrese un tipo de cambio.',
        'compraRepuesto.tipo_cambio.min' => 'Por favor, el tipo de cambio debe ser mayor a 0.',
        'compraRepuesto.cantidad_repuestos.required' => 'Por favor, ingrese la cantidad de repuestos.',
        'compraRepuesto.cantidad_repuestos.min' => 'Por favor, la cantidad de repuestos debe ser mayor a 0.',

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
        if ($ya = $this->yaGuardado(CompraRepuesto::class)) {
            $this->avisarYaGuardado($ya, 'compra de repuestos');

            return redirect()->route('compras.repuestos');
        }

        $stock = new StockRepuestoService();

        try {
            $compraRepuesto = DB::transaction(function () use ($stock) {
                $user = Auth::user();
                $sucursalId = (int) $this->compraRepuesto['sucursal_id'];

                $compraRepuesto = CompraRepuesto::create($this->datosDeIdempotencia() + [
                    'fecha_compra' => $this->compraRepuesto['fecha_compra'],
                    'costo_total' => $this->compraRepuesto['costo_total'],
                    'tipo_cambio' => $this->compraRepuesto['tipo_cambio'],
                    'cantidad_repuestos' => $this->compraRepuesto['cantidad_repuestos'],
                    'user_id' => $user->id,
                    'sucursal_id' => $sucursalId,
                ]);

                // Ordenado por repuesto_id: dos guardados simultaneos con las mismas
                // lineas en distinto orden se bloquean mutuamente en InnoDB.
                foreach (collect($this->detalles)->sortBy('repuesto_id') as $detalle) {
                    $repuesto = Repuesto::find($detalle['repuesto_id']);

                    CompraRepuestoDetalle::create([
                        'repuesto_id' => $repuesto->id,
                        // Tipo congelado, igual que en las ventas.
                        'tipo' => $repuesto->tipo,
                        'costo' => $detalle['costo'],
                        'cantidad' => $detalle['cantidad'],
                        'subtotal' => $detalle['subtotal'],
                        'compra_repuesto_id' => $compraRepuesto->id,
                    ]);

                    $stock->ingresar($repuesto->id, $sucursalId, (int) $detalle['cantidad']);

                    // Por query builder y no $repuesto->save(): el servicio escribio
                    // `cantidad` por fuera, asi que el atributo en memoria quedo
                    // obsoleto y un save() del modelo podria pisar el total.
                    Repuesto::whereKey($repuesto->id)
                        ->update(['tipo_cambio' => $compraRepuesto->tipo_cambio]);
                }

                // Una vez al final y no por movimiento: el mismo repuesto puede
                // moverse dos veces en un guardado.
                $stock->recalcularTotales();

                return $compraRepuesto;
            });
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(CompraRepuesto::class)) {
                $this->avisarYaGuardado($ya, 'compra de repuestos');

                return redirect()->route('compras.repuestos');
            }

            throw $e;
        }

        toastr()->success('Compra de repuesto registrada exitosamente');
        $this->dispatch('refreshCompraRepuestoTable');
        // $this->closeModal();
        return redirect()->route('compras.repuestos');
    }

    public function openRepuestoCreateModal()
    {
        $this->dispatch('openRepuestoCreateModal');
    }

    #[On('refreshRepuestoTable')]
    public function refreshRepuestoTable($repuesto)
    {
        $this->selectRepuesto($repuesto['id']);
    }

    public function closeModal()
    {
        $this->reset();
    }

}
