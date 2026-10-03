<?php

namespace App\Livewire\Venta\Modals;

use App\Models\Venta;
use Livewire\Component;
use Livewire\Attributes\On;

class VentaDetalleModal extends Component
{
    public $openModal = false;
    public $venta;
    
    public function render()
    {
        return view('livewire.venta.modals.venta-detalle-modal');
    }

    #[On('openVentaDetalleModal')]
    public function openModal($id)
    {
        $venta = Venta::find($id);
        $this->venta = $venta;
        $this->openModal = true;
    }

    public function closeModal()
    {
        $this->reset();
    }
}
