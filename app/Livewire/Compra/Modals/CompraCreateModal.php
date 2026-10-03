<?php

namespace App\Livewire\Compra\Modals;

use App\Models\Compra;
use App\Models\ProductoMarca;
use App\Models\Proveedor;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Livewire\Component;
use Livewire\Attributes\On;

class CompraCreateModal extends Component
{
    use GuardadoIdempotenteTrait;

    public $openModal = false;
    public $compra = [];

    protected $rules = [
        'compra.fecha_compra' => 'required|date',
        'compra.proveedor_id' => 'required|numeric',
        'compra.tipo_cambio' => 'required|numeric',
    ];

    protected $messages = [
        'compra.fecha_compra.required' => 'Debe ingresar una fecha de compra',
        'compra.proveedor_id.required' => 'Debe ingresar un proveedor',
        'compra.tipo_cambio.required' => 'Debe ingresar un tipo de cambio',
    ];

    public function render()
    {
        $proveedores = Proveedor::all();
        return view('livewire.compra.modals.compra-create-modal', compact('proveedores'));
    }

    #[On('openCompraCreateModal')]
    public function openModal()
    {
        $ultimaCompra = Compra::latest()->first();
        $this->compra['fecha_compra'] = date('Y-m-d');
        $this->compra['tipo_cambio'] = $ultimaCompra ? $ultimaCompra->tipo_cambio : 7;
        $this->openModal = true;

        // Una clave por apertura: este modal redirige a la pantalla de productos
        // del lote, asi que un doble clic -- o un reintento tras cortarse la
        // red-- creaba DOS lotes vacios y el usuario se quedaba trabajando en
        // uno sin saber del otro.
        $this->nuevaClaveIdempotencia();
    }

    public function store()
    {
        $this->validate();

        // El reintento: se vuelve al lote que ya existe en lugar de abrir otro.
        if ($ya = $this->yaGuardado(Compra::class)) {
            $this->avisarYaGuardado($ya, 'compra de lote');

            return redirect()->route('compras.productos', $ya->id);
        }

        // Un solo INSERT, asi que no hace falta transaccion: lo que faltaba era
        // la clave. Compra es $guarded = ['id'], asi que no hay que tocar
        // ningun $fillable para que la columna entre.
        try {
            $compra = Compra::create($this->compra + $this->datosDeIdempotencia());
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Compra::class)) {
                $this->avisarYaGuardado($ya, 'compra de lote');

                return redirect()->route('compras.productos', $ya->id);
            }

            throw $e;
        }

        toastr()->success('Compra de lote registrada exitosamente');
        return redirect()->route('compras.productos', $compra->id);
    }

    public function closeModal()
    {
        $this->reset();
    }
}
