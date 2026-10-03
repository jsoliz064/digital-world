<?php

namespace App\Livewire\Compra\Modals;

use App\Models\Compra;
use App\Models\Proveedor;
use Livewire\Component;
use Livewire\Attributes\On;

class CompraEditModal extends Component
{

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
        return view('livewire.compra.modals.compra-edit-modal', compact('proveedores'));
    }

    #[On('openCompraEditModal')]
    public function openModal($id)
    {
        $compra = Compra::find($id);
        $this->compra = $compra->toArray();
        $this->openModal = true;
    }

    public function update()
    {
        $this->validate();
        $compra = Compra::find($this->compra['id']);
        $compra->update($this->compra);
        $this->dispatch('refreshCompraTable');
        toastr()->success('Categoria editada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
