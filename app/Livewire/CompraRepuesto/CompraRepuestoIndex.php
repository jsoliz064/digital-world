<?php

namespace App\Livewire\CompraRepuesto;

use Livewire\Component;

class CompraRepuestoIndex extends Component
{

    public function render()
    {
        return view('livewire.compra-repuesto.compra-repuesto-index');
    }

    public function compraRepuestoCreate()
    {
        return redirect()->route('compras.repuestos.crear');
    }
}
