<?php

namespace App\Livewire\CompraLote;

use App\Models\Compra;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El detalle de una compra: su cabecera, los equipos (que se dan de alta aqui,
 * uno por uno, con IMEI y fotos) y los repuestos y accesorios que trajo.
 */
class CompraLoteIndex extends Component
{
    #[Locked]
    public int $compraId;

    public function mount($compra)
    {
        $this->compraId = $compra->id;
    }

    public function openProductoEstadoMasivoModal()
    {
        $this->dispatch('openProductoEstadoMasivoModal', compraId: $this->compraId);
    }

    #[On('refreshCompraDetalle')]
    public function refrescar(): void
    {
        // El render relee la compra: el total cambia al agregar o quitar equipos.
    }

    public function render()
    {
        $compra = Compra::with(['proveedor', 'sucursal', 'user', 'detalles.repuesto', 'detalles.accesorio'])
            ->findOrFail($this->compraId);

        return view('livewire.compra-lote.compra-lote-index', [
            'compra' => $compra,
            'articulos' => $compra->detalles->whereNull('producto_id')->values(),
            'equipos' => $compra->detalles->whereNotNull('producto_id')->count(),
        ]);
    }
}
