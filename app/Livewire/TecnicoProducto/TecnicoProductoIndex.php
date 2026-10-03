<?php

namespace App\Livewire\TecnicoProducto;

use App\Exports\TecnicoProductoExport;
use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;

class TecnicoProductoIndex extends Component
{

    public $tecnico;
    public $cant_productos_pendientes;
    public $cant_productos_no_pagados;
    public $total_productos_no_pagados;

    public function mount($tecnico_id)
    {
        $tecnico = Tecnicos::find($tecnico_id);
        $this->tecnico = $tecnico;
        $this->calculateRepraciones();
    }

    private function calculateRepraciones()
    {
        $productos_pendientes = $this->tecnico->reparaciones()->where('estado', 'pendiente')->get();
        $productos_no_pagados = $this->tecnico->reparaciones()->where('pagado', false)->get();

        $this->cant_productos_pendientes = count($productos_pendientes);
        $this->cant_productos_no_pagados = count($productos_no_pagados);
        $this->total_productos_no_pagados = $productos_no_pagados->sum('costo');
    }

    public function render()
    {
        return view('livewire.tecnico-producto.tecnico-producto-index');
    }

    public function openTecnicoPagoModal($tenico_id)
    {
        $this->dispatch('openTecnicoPagoModal', $tenico_id);
    }

    public function openTecnicoTerminarModal($tenico_id)
    {
        $this->dispatch('openTecnicoTerminarModal', $tenico_id);
    }

    #[On('refreshTecnicoProductoIndex')]
    public function refreshTecnicoProductoIndex()
    {
        $this->calculateRepraciones();
    }

    public function exportProductosExcel()
    {
        $now = date('Hi');
        return (new TecnicoProductoExport($this->tecnico))
            ->download("productos-pendientes-{$this->tecnico->nombre}-{$now}.xlsx");
    }
}
