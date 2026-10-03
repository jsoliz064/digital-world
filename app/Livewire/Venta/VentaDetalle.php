<?php

namespace App\Livewire\Venta;

use App\Models\Venta;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** La pagina de detalle de una venta: cabecera, totales y sus lineas. */
class VentaDetalle extends Component
{
    #[Locked]
    public int $ventaId;

    public function mount($venta)
    {
        $this->ventaId = $venta->id;
    }

    public function render()
    {
        // La carga va en render(): Livewire rehidrata por id y sin relaciones.
        $venta = Venta::with(['user', 'sucursal', 'fichaCliente'])->withCount('detalles')->find($this->ventaId);

        if (!$venta) {
            // Anularon la ultima linea: la venta ya no existe.
            return view('livewire.venta.venta-anulada');
        }

        return view('livewire.venta.venta-detalle', ['venta' => $venta]);
    }

    public function ventaEditar()
    {
        return redirect()->route('ventas.editar', $this->ventaId);
    }

    public function anularVenta()
    {
        $this->dispatch('openVentaDestroyModal', $this->ventaId);
    }

    #[On('refreshVentaDetalle')]
    public function refreshVentaDetalle()
    {
    }
}
