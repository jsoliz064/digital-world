<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Producto;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoDestroyModal extends Component
{
    public $openModal = false;
    public $producto;

    #[On('openProductoDestroyModal')]
    public function openModal($id)
    {
        $this->producto = Producto::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $this->producto->delete();
            $this->dispatch('refreshProductoTable');
            toastr()->success('Categoria eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar la categoria');
        }
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto.modals.producto-destroy-modal');
    }
}
