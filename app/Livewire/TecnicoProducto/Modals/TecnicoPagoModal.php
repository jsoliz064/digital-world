<?php

namespace App\Livewire\TecnicoProducto\Modals;

use App\Models\ProductoReparacion;
use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;

class TecnicoPagoModal extends Component
{
    public $openModal = false;
    public $tecnico;
    public $reparaciones;

    public function render()
    {
        $total = $this->reparaciones ? $this->reparaciones->sum('costo') : 0;
        return view('livewire.tecnico-producto.modals.tecnico-pago-modal', [
            'total' => $total
        ]);
    }

    #[On('openTecnicoPagoModal')]
    public function openModal($tecnico_id)
    {
        $tecnico = Tecnicos::find($tecnico_id);
        $this->tecnico = $tecnico;
        $this->reparaciones = $tecnico->reparaciones()->where('pagado', false)->get();
        $this->openModal = true;
    }

    public function quitarReparacion($reparacionId)
    {
        if ($this->reparaciones) {
            $this->reparaciones = $this->reparaciones->where('id', '!=', $reparacionId);
        }
    }

    public function marcarComoPagado()
    {
        if ($this->reparaciones->isEmpty()) {
            toastr()->warning('No hay reparaciones seleccionadas para pagar.');
            return;
        }

        $reparacionIds = $this->reparaciones->pluck('id');

        try {
            ProductoReparacion::whereIn('id', $reparacionIds)->update(['pagado' => true]);

            toastr()->success('Pagos registrados exitosamente.');

            $this->dispatch('refreshTecnicoProductoTable');
            $this->dispatch('refreshTecnicoProductoIndex');

            $this->closeModal();
        } catch (\Exception $e) {
            toastr()->error('Ocurrió un error al procesar el pago.');
        }
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'tecnico', 'reparaciones']);
    }
}
