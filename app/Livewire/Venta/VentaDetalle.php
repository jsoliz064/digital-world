<?php

namespace App\Livewire\Venta;

use Livewire\Attributes\On;
use Livewire\Component;

class VentaDetalle extends Component
{
    public $venta;

    public function mount($venta)
    {
        $this->venta = $venta;
    }

    public function render()
    {
        // La carga va en render() y no en mount(): Livewire rehidrata el modelo
        // por su id entre peticiones y las relaciones se pierden por el camino.
        $this->venta?->load([
            'ventasRepuestos.detalles.repuesto',
            'ventasRepuestos.detalles.reparacionRepuesto',
        ]);

        return view('livewire.venta.venta-detalle');
    }

    public function ventaEditar($id)
    {
        return redirect()->route('ventas.editar', $id);
    }

    #[On('refreshVentaDetalle')]
    public function refreshVentaDetalle()
    {
        $this->venta->refresh();
    }
}
