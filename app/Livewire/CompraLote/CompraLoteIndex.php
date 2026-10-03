<?php

namespace App\Livewire\CompraLote;

use Livewire\Component;

class CompraLoteIndex extends Component
{
    public $compra_lote;


    public function mount($compra)
    {
        $this->compra_lote = $compra;
    }

    public function openProductoEstadoMasivoModal()
    {
        $this->dispatch('openProductoEstadoMasivoModal', compraId: $this->compra_lote->id);
    }

    public function render()
    {
        return view('livewire.compra-lote.compra-lote-index');
    }
}
