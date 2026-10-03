<?php

namespace App\Livewire\CompraLote\Modals;

use App\Models\Producto;
use Livewire\Component;
use Livewire\Attributes\On;


class CompraLoteProductoDestroyModal extends Component
{
    public $openModal = false;
    public $producto;

    #[On('openCompraLoteProductoDestroyModal')]
    public function openModal($id)
    {
        $this->producto = Producto::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            $compra = $this->producto->compra;
            $this->producto->delete();
            $compra->recalculate();
            toastr()->success('Producto eliminado exitosamente');
            $this->deleteAndClose();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el producto' . $th->getMessage());
        }
    }

    public function deleteAndClose()
    {
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }


    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.compra-lote.modals.compra-lote-producto-destroy-modal');
    }
}
