<?php

namespace App\Livewire\Repuesto\Modals;

use App\Models\Repuesto;
use App\Models\RepuestoSucursal;
use Livewire\Component;
use Livewire\Attributes\On;

class RepuestoDestroyModal extends Component
{
    public $openModal = false;
    public $repuesto;

    #[On('openRepuestoDestroyModal')]
    public function openModal($id)
    {
        $this->repuesto = Repuesto::with(['modelo', 'categoria', 'stocks.sucursal'])->find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        try {
            // El stock se va con la ficha: repuestos_sucursales cae en cascada,
            // en silencio, y `cantidad` no entra en el retrato del observer. Si
            // no se anota aqui, despues no hay forma de saber con cuantas
            // unidades se borro ni donde estaban.
            $this->repuesto->anotar('eliminado', $this->stockAlBorrar());
            $this->repuesto->delete();
            $this->dispatch('refreshRepuestoTable');
            toastr()->success('Repuesto eliminado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error('Error al eliminar el repuesto');
        }
    }

    /**
     * "Se borro con 7 unidades: Almacen 5, Centro 2."
     *
     * Consulta y no la relacion: Livewire rehidrata $repuesto por su id y SIN
     * relaciones, asi que el `stocks` que cargo openModal() ya no esta aqui.
     */
    protected function stockAlBorrar(): string
    {
        $reparto = RepuestoSucursal::with('sucursal:id,nombre')
            ->where('repuesto_id', $this->repuesto->id)
            ->where('cantidad', '<>', 0)
            ->orderByDesc('cantidad')
            ->get();

        if ($reparto->isEmpty()) {
            return 'Se borró sin stock.';
        }

        $detalle = $reparto
            ->map(fn($s) => ($s->sucursal?->nombre ?? 'Sin sucursal') . ' ' . (int) $s->cantidad)
            ->implode(', ');

        return 'Se borró con ' . (int) $reparto->sum('cantidad') . " unidades: {$detalle}.";
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.repuesto.modals.repuesto-destroy-modal');
    }
}
